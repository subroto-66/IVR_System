<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IvrSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IvrSettingController extends Controller
{
    /**
     * Display IVR system configuration settings.
     */
    public function index(): View
    {
        $settings = [
            // Core IVR timing & limits
            'max_retries' => IvrSetting::get('max_retries', 3),
            'gather_timeout' => IvrSetting::get('gather_timeout', 5),
            'return_to_menu_digit' => IvrSetting::get('return_to_menu_digit', '9'),
            'return_to_menu_prompt_text' => IvrSetting::get('return_to_menu_prompt_text', 'To return to the main menu, press 9.'),

            // SMS Configuration
            'sms_enabled' => filter_var(IvrSetting::get('sms_enabled', true), FILTER_VALIDATE_BOOLEAN),
            'sms_message' => IvrSetting::get('sms_message', 'Thank you for your interest! Visit our portal at: https://example.com/apply for more details.'),
            'sms_confirmation_fallback_text' => IvrSetting::get('sms_confirmation_fallback_text', 'You will receive the information by text message shortly.'),

            // Speech fallback texts
            'welcome_fallback_text' => IvrSetting::get('welcome_fallback_text', 'Welcome to our automated information and recruitment line.'),
            'main_presentation_fallback_text' => IvrSetting::get('main_presentation_fallback_text', 'Thank you for your interest in our company and career opportunities.'),
            'menu_fallback_text' => IvrSetting::get('menu_fallback_text', ''),
            'invalid_fallback_text' => IvrSetting::get('invalid_fallback_text', 'Sorry, that is not a valid selection. Please try again.'),
            'no_input_fallback_text' => IvrSetting::get('no_input_fallback_text', 'We did not receive a selection. Please choose an option.'),
            'goodbye_fallback_text' => IvrSetting::get('goodbye_fallback_text', 'Thank you for calling. Goodbye.'),
        ];

        return view('pages.admin.settings.index', [
            'title' => 'IVR System Settings',
            'settings' => $settings,
        ]);
    }

    /**
     * Update IVR system settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'max_retries' => ['required', 'integer', 'min:1', 'max:10'],
            'gather_timeout' => ['required', 'integer', 'min:2', 'max:30'],
            'return_to_menu_digit' => ['required', 'string', 'max:2'],
            'return_to_menu_prompt_text' => ['required', 'string', 'max:500'],
            'sms_enabled' => ['nullable', 'boolean'],
            'sms_message' => ['required', 'string', 'max:1600'],
            'sms_confirmation_fallback_text' => ['required', 'string', 'max:500'],
            'welcome_fallback_text' => ['nullable', 'string', 'max:1000'],
            'main_presentation_fallback_text' => ['nullable', 'string', 'max:2000'],
            'menu_fallback_text' => ['nullable', 'string', 'max:2000'],
            'invalid_fallback_text' => ['nullable', 'string', 'max:500'],
            'no_input_fallback_text' => ['nullable', 'string', 'max:500'],
            'goodbye_fallback_text' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($validated as $key => $value) {
            IvrSetting::set($key, $value);
        }

        // Handle checkbox for boolean sms_enabled if not checked
        if (!$request->has('sms_enabled')) {
            IvrSetting::set('sms_enabled', '0');
        }

        IvrSetting::clearCache();

        return back()->with('success', 'IVR settings updated successfully.');
    }
}
