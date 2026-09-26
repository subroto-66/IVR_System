<?php

namespace Database\Seeders;

use App\Models\IvrOption;
use App\Models\IvrSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class IvrSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create or ensure Default Admin User
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // 2. Default IVR Settings
        $settings = [
            // Audio paths (initially null until uploaded by client)
            ['key' => 'welcome_audio_path', 'value' => null, 'type' => 'audio', 'group' => 'audio', 'label' => 'Welcome Audio'],
            ['key' => 'main_presentation_audio_path', 'value' => null, 'type' => 'audio', 'group' => 'audio', 'label' => 'Main Presentation Audio'],
            ['key' => 'menu_prompt_audio_path', 'value' => null, 'type' => 'audio', 'group' => 'audio', 'label' => 'Menu Instructions Audio'],
            ['key' => 'invalid_input_audio_path', 'value' => null, 'type' => 'audio', 'group' => 'audio', 'label' => 'Invalid Selection Audio'],
            ['key' => 'no_input_audio_path', 'value' => null, 'type' => 'audio', 'group' => 'audio', 'label' => 'No Response Audio'],
            ['key' => 'goodbye_audio_path', 'value' => null, 'type' => 'audio', 'group' => 'audio', 'label' => 'Goodbye Audio'],

            // Text-to-speech fallbacks
            ['key' => 'welcome_fallback_text', 'value' => 'Welcome to our automated information and recruitment line.', 'type' => 'textarea', 'group' => 'prompts', 'label' => 'Welcome Fallback Text'],
            ['key' => 'main_presentation_fallback_text', 'value' => 'Thank you for your interest in our company and career opportunities. We are dedicated to building exceptional products and empowering great talent.', 'type' => 'textarea', 'group' => 'prompts', 'label' => 'Main Presentation Fallback Text'],
            ['key' => 'menu_fallback_text', 'value' => '', 'type' => 'textarea', 'group' => 'prompts', 'label' => 'Menu Fallback Text (empty auto-generates options)'],
            ['key' => 'invalid_fallback_text', 'value' => 'Sorry, that is not a valid selection. Please try again.', 'type' => 'text', 'group' => 'prompts', 'label' => 'Invalid Selection Fallback Text'],
            ['key' => 'no_input_fallback_text', 'value' => 'We did not receive a selection. Please choose an option.', 'type' => 'text', 'group' => 'prompts', 'label' => 'No Response Fallback Text'],
            ['key' => 'goodbye_fallback_text', 'value' => 'Thank you for calling our information line. Have a great day. Goodbye.', 'type' => 'text', 'group' => 'prompts', 'label' => 'Goodbye Fallback Text'],

            // Core flow settings
            ['key' => 'max_retries', 'value' => '3', 'type' => 'number', 'group' => 'general', 'label' => 'Max Retries Before Disconnect'],
            ['key' => 'gather_timeout', 'value' => '5', 'type' => 'number', 'group' => 'general', 'label' => 'Keypad Input Timeout (seconds)'],
            ['key' => 'return_to_menu_digit', 'value' => '9', 'type' => 'text', 'group' => 'general', 'label' => 'Return to Menu Keypad Digit'],
            ['key' => 'return_to_menu_prompt_text', 'value' => 'To return to the main menu, press 9.', 'type' => 'text', 'group' => 'prompts', 'label' => 'Return to Menu Prompt'],

            // SMS Configuration
            ['key' => 'sms_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'sms', 'label' => 'Enable SMS Information Feature'],
            ['key' => 'sms_message', 'value' => 'Thank you for calling! Learn more about our company and apply online at: https://example.com/careers', 'type' => 'textarea', 'group' => 'sms', 'label' => 'Default SMS Information Message'],
            ['key' => 'sms_confirmation_fallback_text', 'value' => 'You will receive the information by text message shortly.', 'type' => 'text', 'group' => 'sms', 'label' => 'SMS Confirmation Voice Message'],
        ];

        foreach ($settings as $setting) {
            IvrSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        // 3. Default IVR Options (1 through 5)
        $defaultOptions = [
            [
                'digit' => '1',
                'title' => 'Company Information',
                'description' => 'Overview of the organization, core mission, and operational reach.',
                'audio_path' => null,
                'audio_url' => null,
                'action_type' => 'audio',
                'sms_enabled' => false,
                'fallback_text' => 'Our company is an industry leader providing cutting edge solutions. We operate globally with a mission to deliver world class services to our clients.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'digit' => '2',
                'title' => 'Recruitment Information',
                'description' => 'Current hiring positions, qualifications, and employment benefits.',
                'audio_path' => null,
                'audio_url' => null,
                'action_type' => 'audio',
                'sms_enabled' => false,
                'fallback_text' => 'We are actively hiring talented professionals across multiple departments. We offer competitive salaries, health coverage, and remote flexibility.',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'digit' => '3',
                'title' => 'Program Information',
                'description' => 'Details regarding our specialized training and development programs.',
                'audio_path' => null,
                'audio_url' => null,
                'action_type' => 'audio',
                'sms_enabled' => false,
                'fallback_text' => 'Our development program features hands-on mentorship, paid apprenticeships, and direct pathways to full-time career advancement.',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'digit' => '4',
                'title' => 'Additional Information',
                'description' => 'Frequently asked questions, office hours, and contact details.',
                'audio_path' => null,
                'audio_url' => null,
                'action_type' => 'audio',
                'sms_enabled' => false,
                'fallback_text' => 'For additional inquiries, our offices are open Monday through Friday from 9 AM to 5 PM. You can also visit our website anytime.',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'digit' => '5',
                'title' => 'Send Information by SMS',
                'description' => 'Automatically delivers recruitment portal link to caller phone via text message.',
                'audio_path' => null,
                'audio_url' => null,
                'action_type' => 'sms',
                'sms_enabled' => true,
                'sms_message' => 'Thank you for your interest! Explore open roles and submit your application at: https://example.com/apply',
                'fallback_text' => 'You will receive our recruitment portal link by text message shortly.',
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($defaultOptions as $optData) {
            IvrOption::firstOrCreate(
                ['digit' => $optData['digit']],
                $optData
            );
        }
    }
}
