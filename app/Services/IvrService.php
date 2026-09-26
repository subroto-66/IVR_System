<?php

namespace App\Services;

use App\Models\IvrCallLog;
use App\Models\IvrOption;
use App\Models\IvrSetting;
use Exception;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

class IvrService
{
    protected TwilioService $twilioService;
    protected AudioStorageService $audioStorageService;

    public function __construct(TwilioService $twilioService, AudioStorageService $audioStorageService)
    {
        $this->twilioService = $twilioService;
        $this->audioStorageService = $audioStorageService;
    }

    /**
     * Build the initial inbound call response (Welcome + Main Presentation + Menu Gather).
     */
    public function buildInitialResponse(IvrCallLog $callLog): VoiceResponse
    {
        $response = new VoiceResponse();

        // 1. Welcome Audio / Fallback
        $welcomeAudioPath = IvrSetting::get('welcome_audio_path');
        $welcomeText = IvrSetting::get('welcome_fallback_text', 'Welcome to our automated information and recruitment line.');

        if (!empty($welcomeAudioPath)) {
            $response->play($this->audioStorageService->getUrl($welcomeAudioPath));
        } elseif (!empty($welcomeText)) {
            $response->say($welcomeText, ['voice' => 'Polly.Joanna']);
        }

        // 2. Main Presentation Audio / Fallback
        $mainPresAudioPath = IvrSetting::get('main_presentation_audio_path');
        $mainPresText = IvrSetting::get('main_presentation_fallback_text', 'Thank you for your interest in our company and career opportunities.');

        if (!empty($mainPresAudioPath)) {
            $response->play($this->audioStorageService->getUrl($mainPresAudioPath));
        } elseif (!empty($mainPresText)) {
            $response->say($mainPresText, ['voice' => 'Polly.Joanna']);
        }

        // 3. Attach Main Menu Gather
        $this->appendMenuGather($response, 0);

        $callLog->recordEvent('welcome_and_menu_played');

        return $response;
    }

    /**
     * Build a standalone Main Menu response (for returning to menu or after retry).
     */
    public function buildMenuResponse(IvrCallLog $callLog, int $retryCount = 0): VoiceResponse
    {
        $response = new VoiceResponse();
        $this->appendMenuGather($response, $retryCount);

        $callLog->recordEvent('menu_presented', ['retry_count' => $retryCount]);

        return $response;
    }

    /**
     * Process DTMF input from caller.
     */
    public function processInput(string $digit, ?string $fromNumber, IvrCallLog $callLog, int $retryCount = 0): VoiceResponse
    {
        $returnDigit = (string) IvrSetting::get('return_to_menu_digit', '9');
        $maxRetries = (int) IvrSetting::get('max_retries', 3);

        $callLog->recordEvent('dtmf_received', [
            'digit' => $digit,
            'retry_count' => $retryCount,
        ]);

        // Check if caller wants to return to the main menu
        if ($digit === $returnDigit) {
            $callLog->update(['selected_option' => "Return to Menu ({$returnDigit})"]);
            return $this->buildMenuResponse($callLog, 0);
        }

        // Look up active option by digit
        $option = IvrOption::active()->where('digit', $digit)->first();

        if ($option) {
            $callLog->update(['selected_option' => "{$option->digit} - {$option->title}"]);
            return $this->executeOption($option, $fromNumber, $callLog);
        }

        // Invalid digit pressed
        return $this->handleInvalidInput($callLog, $retryCount + 1, $maxRetries);
    }

    /**
     * Execute the selected IVR option action.
     */
    protected function executeOption(IvrOption $option, ?string $fromNumber, IvrCallLog $callLog): VoiceResponse
    {
        $response = new VoiceResponse();

        // Case 1: SMS Action
        if ($option->action_type === 'sms' || $option->sms_enabled) {
            $this->handleSmsOption($option, $fromNumber, $response, $callLog);
            return $response;
        }

        // Case 2: Goodbye Action
        if ($option->action_type === 'goodbye') {
            return $this->buildGoodbyeResponse($callLog, 'option_selected');
        }

        // Case 3: Menu Action
        if ($option->action_type === 'menu') {
            return $this->buildMenuResponse($callLog, 0);
        }

        // Case 4: Audio Information Playback
        $audioUrl = $option->resolved_audio_url;

        if (!empty($audioUrl)) {
            $response->play($audioUrl);
        } elseif (!empty($option->fallback_text)) {
            $response->say($option->fallback_text, ['voice' => 'Polly.Joanna']);
        } else {
            $response->say("You selected {$option->title}.", ['voice' => 'Polly.Joanna']);
        }

        // After playback, allow caller to return to main menu or select another option
        $returnDigit = (string) IvrSetting::get('return_to_menu_digit', '9');
        $returnPrompt = IvrSetting::get('return_to_menu_prompt_text', "To return to the main menu, press {$returnDigit}.");
        $timeout = (int) IvrSetting::get('gather_timeout', 5);

        $gather = $response->gather([
            'numDigits' => 1,
            'action' => route('twilio.menu') . '?retry=0',
            'method' => 'POST',
            'timeout' => $timeout,
        ]);

        $gather->say($returnPrompt, ['voice' => 'Polly.Joanna']);

        // If no input after post-option prompt, default back to menu or end gently
        $response->say('Returning to the main menu.', ['voice' => 'Polly.Joanna']);
        $this->appendMenuGather($response, 0);

        return $response;
    }

    /**
     * Handle SMS action for caller.
     */
    protected function handleSmsOption(IvrOption $option, ?string $fromNumber, VoiceResponse $response, IvrCallLog $callLog): void
    {
        $smsEnabled = filter_var(IvrSetting::get('sms_enabled', true), FILTER_VALIDATE_BOOLEAN);
        $smsMessage = !empty($option->sms_message)
            ? $option->sms_message
            : IvrSetting::get('sms_message', 'Thank you for your interest! Visit our portal at: https://example.com/apply for more details.');

        $confirmationText = IvrSetting::get('sms_confirmation_fallback_text', 'You will receive the information by text message shortly.');

        if ($smsEnabled && !empty($fromNumber)) {
            $result = $this->twilioService->sendSms($fromNumber, $smsMessage);

            if ($result['success']) {
                $callLog->recordEvent('sms_sent', [
                    'to' => $fromNumber,
                    'message_sid' => $result['message_sid'],
                ]);
            } else {
                $callLog->recordEvent('sms_failed', [
                    'to' => $fromNumber,
                    'error' => $result['error'],
                ]);
                Log::warning("Twilio IVR SMS failed to send to {$fromNumber}: " . $result['error']);
            }
        } else {
            $callLog->recordEvent('sms_skipped', [
                'sms_enabled' => $smsEnabled,
                'has_from_number' => !empty($fromNumber),
            ]);
        }

        // Audio or TTS confirmation
        $response->say($confirmationText, ['voice' => 'Polly.Joanna']);

        // Offer return to main menu
        $returnDigit = (string) IvrSetting::get('return_to_menu_digit', '9');
        $returnPrompt = IvrSetting::get('return_to_menu_prompt_text', "To return to the main menu, press {$returnDigit}.");
        $timeout = (int) IvrSetting::get('gather_timeout', 5);

        $gather = $response->gather([
            'numDigits' => 1,
            'action' => route('twilio.menu') . '?retry=0',
            'method' => 'POST',
            'timeout' => $timeout,
        ]);
        $gather->say($returnPrompt, ['voice' => 'Polly.Joanna']);

        // Default back to menu
        $this->appendMenuGather($response, 0);
    }

    /**
     * Handle invalid keypad selection.
     */
    public function handleInvalidInput(IvrCallLog $callLog, int $newRetryCount, int $maxRetries): VoiceResponse
    {
        $response = new VoiceResponse();

        $callLog->recordEvent('invalid_digit_pressed', [
            'retry_count' => $newRetryCount,
            'max_retries' => $maxRetries,
        ]);

        if ($newRetryCount >= $maxRetries) {
            return $this->buildGoodbyeResponse($callLog, 'max_invalid_retries_exceeded');
        }

        $invalidAudioPath = IvrSetting::get('invalid_input_audio_path');
        $invalidText = IvrSetting::get('invalid_fallback_text', 'Sorry, that is not a valid selection. Please try again.');

        if (!empty($invalidAudioPath)) {
            $response->play($this->audioStorageService->getUrl($invalidAudioPath));
        } else {
            $response->say($invalidText, ['voice' => 'Polly.Joanna']);
        }

        $this->appendMenuGather($response, $newRetryCount);

        return $response;
    }

    /**
     * Handle timeout / no input entered.
     */
    public function handleTimeout(IvrCallLog $callLog, int $newRetryCount): VoiceResponse
    {
        $response = new VoiceResponse();
        $maxRetries = (int) IvrSetting::get('max_retries', 3);

        $callLog->recordEvent('no_input_timeout', [
            'retry_count' => $newRetryCount,
            'max_retries' => $maxRetries,
        ]);

        if ($newRetryCount >= $maxRetries) {
            return $this->buildGoodbyeResponse($callLog, 'max_timeout_retries_exceeded');
        }

        $noInputAudioPath = IvrSetting::get('no_input_audio_path');
        $noInputText = IvrSetting::get('no_input_fallback_text', 'We did not receive a selection. Please choose an option.');

        if (!empty($noInputAudioPath)) {
            $response->play($this->audioStorageService->getUrl($noInputAudioPath));
        } else {
            $response->say($noInputText, ['voice' => 'Polly.Joanna']);
        }

        $this->appendMenuGather($response, $newRetryCount);

        return $response;
    }

    /**
     * Build goodbye response and terminate call.
     */
    public function buildGoodbyeResponse(IvrCallLog $callLog, string $reason = 'normal'): VoiceResponse
    {
        $response = new VoiceResponse();

        $goodbyeAudioPath = IvrSetting::get('goodbye_audio_path');
        $goodbyeText = IvrSetting::get('goodbye_fallback_text', 'Thank you for calling. Goodbye.');

        if (!empty($goodbyeAudioPath)) {
            $response->play($this->audioStorageService->getUrl($goodbyeAudioPath));
        } else {
            $response->say($goodbyeText, ['voice' => 'Polly.Joanna']);
        }

        $response->hangup();

        $callLog->recordEvent('call_ended_goodbye', ['reason' => $reason]);

        return $response;
    }

    /**
     * Append a Gather element with active menu options and fallback timeout handling.
     */
    protected function appendMenuGather(VoiceResponse $response, int $retryCount = 0): void
    {
        $timeout = (int) IvrSetting::get('gather_timeout', 5);
        $actionUrl = route('twilio.menu') . '?retry=' . $retryCount;

        $gather = $response->gather([
            'numDigits' => 1,
            'action' => $actionUrl,
            'method' => 'POST',
            'timeout' => $timeout,
        ]);

        // Menu instructions: Audio or dynamic Say prompt
        $menuPromptAudioPath = IvrSetting::get('menu_prompt_audio_path');

        if (!empty($menuPromptAudioPath)) {
            $gather->play($this->audioStorageService->getUrl($menuPromptAudioPath));
        } else {
            $menuPromptText = IvrSetting::get('menu_fallback_text');

            if (!empty($menuPromptText)) {
                $gather->say($menuPromptText, ['voice' => 'Polly.Joanna']);
            } else {
                // Generate menu text dynamically from active database options
                $activeOptions = IvrOption::active()->ordered()->get();
                $promptLines = [];
                foreach ($activeOptions as $opt) {
                    $promptLines[] = "Press {$opt->digit} for {$opt->title}.";
                }

                $dynamicPrompt = !empty($promptLines)
                    ? implode(' ', $promptLines)
                    : 'Please select an option from your keypad.';

                $gather->say($dynamicPrompt, ['voice' => 'Polly.Joanna']);
            }
        }

        // If caller enters nothing within the Gather timeout, Twilio executes the verbs following Gather
        // We redirect to /twilio/menu with retry incremented and no Digits
        $timeoutUrl = route('twilio.menu') . '?retry=' . ($retryCount + 1) . '&timeout=1';
        $response->redirect($timeoutUrl, ['method' => 'POST']);
    }
}
