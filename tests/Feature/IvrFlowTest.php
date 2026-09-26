<?php

use App\Models\IvrCallLog;
use App\Models\IvrOption;
use App\Models\IvrSetting;
use App\Services\TwilioService;

beforeEach(function () {
    // Ensure default settings & options exist
    $this->seed(\Database\Seeders\IvrSystemSeeder::class);
    // Disable signature validation for local tests
    config(['twilio.webhook_validation' => false]);
});

test('incoming call endpoint returns valid TwiML with welcome and gather', function () {
    $response = $this->post('/twilio/voice', [
        'CallSid' => 'CA_TEST_001',
        'From' => '+15551234567',
        'To' => '+15559876543',
        'Direction' => 'inbound',
    ]);

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/xml; charset=utf-8');

    $content = $response->getContent();
    expect($content)->toContain('<Response>')
        ->toContain('<Gather')
        ->toContain('numDigits="1"');

    // Verify call log was created
    $log = IvrCallLog::where('call_sid', 'CA_TEST_001')->first();
    expect($log)->not->toBeNull()
        ->and($log->from_number)->toBe('+15551234567');
});

test('valid digit 1 plays option 1 audio or fallback text', function () {
    $response = $this->post('/twilio/menu', [
        'CallSid' => 'CA_TEST_002',
        'From' => '+15551234567',
        'Digits' => '1',
    ]);

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/xml; charset=utf-8');

    $content = $response->getContent();
    // Default fallback text or title for option 1 (Company Information)
    expect($content)->toContain('Company')
        ->toContain('To return to the main menu, press 9');

    $log = IvrCallLog::where('call_sid', 'CA_TEST_002')->first();
    expect($log->selected_option)->toContain('1 - Company Information');
});

test('pressing return digit 9 returns caller to main menu', function () {
    $response = $this->post('/twilio/menu', [
        'CallSid' => 'CA_TEST_003',
        'From' => '+15551234567',
        'Digits' => '9',
    ]);

    $response->assertStatus(200);
    $content = $response->getContent();
    expect($content)->toContain('<Gather')
        ->toContain('numDigits="1"');
});

test('valid digit 2 plays option 2 recruitment information', function () {
    $response = $this->post('/twilio/menu', [
        'CallSid' => 'CA_TEST_004',
        'From' => '+15551234567',
        'Digits' => '2',
    ]);

    $response->assertStatus(200);
    $content = $response->getContent();
    expect($content)->toContain('hiring')
        ->toContain('To return to the main menu, press 9');
});

test('invalid keypad digit plays invalid selection message and retries menu', function () {
    $response = $this->post('/twilio/menu?retry=0', [
        'CallSid' => 'CA_TEST_005',
        'From' => '+15551234567',
        'Digits' => '8', // Digit 8 does not exist
    ]);

    $response->assertStatus(200);
    $content = $response->getContent();
    expect($content)->toContain('not a valid selection')
        ->toContain('<Gather');
});

test('exceeding max retries with invalid input plays goodbye and hangs up', function () {
    $maxRetries = (int) IvrSetting::get('max_retries', 3);

    // Call with retry count at limit
    $response = $this->post("/twilio/menu?retry={$maxRetries}", [
        'CallSid' => 'CA_TEST_006',
        'From' => '+15551234567',
        'Digits' => '8',
    ]);

    $response->assertStatus(200);
    $content = $response->getContent();
    expect($content)->toContain('Goodbye')
        ->toContain('<Hangup/>');
});

test('no input / timeout plays timeout message and retries menu', function () {
    $response = $this->post('/twilio/menu?retry=0&timeout=1', [
        'CallSid' => 'CA_TEST_007',
        'From' => '+15551234567',
    ]);

    $response->assertStatus(200);
    $content = $response->getContent();
    expect($content)->toContain('did not receive a selection')
        ->toContain('<Gather');
});

test('exceeding timeout retries terminates call with goodbye', function () {
    $maxRetries = (int) IvrSetting::get('max_retries', 3);

    $response = $this->post("/twilio/menu?retry={$maxRetries}&timeout=1", [
        'CallSid' => 'CA_TEST_008',
        'From' => '+15551234567',
    ]);

    $response->assertStatus(200);
    $content = $response->getContent();
    expect($content)->toContain('Goodbye')
        ->toContain('<Hangup/>');
});

test('SMS option 5 triggers TwilioService and confirms to caller', function () {
    $mockTwilio = Mockery::mock(TwilioService::class);
    $mockTwilio->shouldReceive('sendSms')
        ->once()
        ->with('+15551234567', Mockery::type('string'))
        ->andReturn([
            'success' => true,
            'message_sid' => 'SM_MOCK_12345',
            'error' => null,
        ]);

    $this->app->instance(TwilioService::class, $mockTwilio);

    $response = $this->post('/twilio/menu', [
        'CallSid' => 'CA_TEST_009',
        'From' => '+15551234567',
        'Digits' => '5',
    ]);

    $response->assertStatus(200);
    $content = $response->getContent();
    expect($content)->toContain('You will receive the information by text message shortly')
        ->toContain('To return to the main menu, press 9');

    $log = IvrCallLog::where('call_sid', 'CA_TEST_009')->first();
    expect($log->metadata['events'])->not->toBeEmpty();
});

test('SMS option handles missing caller number gracefully', function () {
    $response = $this->post('/twilio/menu', [
        'CallSid' => 'CA_TEST_010',
        'From' => null, // Anonymous or missing number
        'Digits' => '5',
    ]);

    $response->assertStatus(200);
    $content = $response->getContent();
    expect($content)->toContain('You will receive the information by text message shortly');
});

test('call status callback updates duration and completed status', function () {
    IvrCallLog::create([
        'call_sid' => 'CA_STATUS_TEST',
        'from_number' => '+15551234567',
        'status' => 'in-progress',
    ]);

    $response = $this->post('/twilio/status', [
        'CallSid' => 'CA_STATUS_TEST',
        'CallStatus' => 'completed',
        'CallDuration' => '78',
    ]);

    $response->assertStatus(200);

    $log = IvrCallLog::where('call_sid', 'CA_STATUS_TEST')->first();
    expect($log->status)->toBe('completed')
        ->and($log->duration)->toBe(78)
        ->and($log->ended_at)->not->toBeNull();
});

test('audio replacement changes playback dynamically without code changes', function () {
    $option = IvrOption::where('digit', '1')->first();
    $option->update([
        'audio_url' => 'https://storage.example.com/audio/new-recording-v2.mp3',
    ]);

    $response = $this->post('/twilio/menu', [
        'CallSid' => 'CA_AUDIO_TEST',
        'From' => '+15551234567',
        'Digits' => '1',
    ]);

    $response->assertStatus(200);
    $content = $response->getContent();
    // TwiML <Play> must contain the new URL immediately
    expect($content)->toContain('<Play>https://storage.example.com/audio/new-recording-v2.mp3</Play>');
});
