<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AudioUploadRequest;
use App\Models\IvrOption;
use App\Models\IvrSetting;
use App\Services\AudioStorageService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AudioManagementController extends Controller
{
    protected AudioStorageService $audioStorageService;

    public function __construct(AudioStorageService $audioStorageService)
    {
        $this->audioStorageService = $audioStorageService;
    }

    /**
     * Display the centralized Audio Management interface.
     */
    public function index(): View
    {
        // 1. System-level audio entries
        $systemAudioSlots = [
            [
                'key' => 'welcome_audio_path',
                'target_type' => 'welcome',
                'title' => 'Welcome / Introduction',
                'description' => 'Plays immediately when caller dials the number.',
                'path' => IvrSetting::get('welcome_audio_path'),
                'fallback' => IvrSetting::get('welcome_fallback_text'),
            ],
            [
                'key' => 'main_presentation_audio_path',
                'target_type' => 'main_presentation',
                'title' => 'Main Presentation',
                'description' => 'Plays right after the welcome audio before the keypad menu.',
                'path' => IvrSetting::get('main_presentation_audio_path'),
                'fallback' => IvrSetting::get('main_presentation_fallback_text'),
            ],
            [
                'key' => 'menu_prompt_audio_path',
                'target_type' => 'menu_prompt',
                'title' => 'Main Menu Prompt',
                'description' => 'Instructions for the caller (e.g. "Press 1 for..."). Leave empty to auto-generate from active options.',
                'path' => IvrSetting::get('menu_prompt_audio_path'),
                'fallback' => IvrSetting::get('menu_fallback_text'),
            ],
            [
                'key' => 'invalid_input_audio_path',
                'target_type' => 'invalid_input',
                'title' => 'Invalid Selection Audio',
                'description' => 'Plays when caller presses an unrecognized key.',
                'path' => IvrSetting::get('invalid_input_audio_path'),
                'fallback' => IvrSetting::get('invalid_fallback_text'),
            ],
            [
                'key' => 'no_input_audio_path',
                'target_type' => 'no_input',
                'title' => 'No Response / Timeout Audio',
                'description' => 'Plays when caller does not press any key before timeout.',
                'path' => IvrSetting::get('no_input_audio_path'),
                'fallback' => IvrSetting::get('no_input_fallback_text'),
            ],
            [
                'key' => 'goodbye_audio_path',
                'target_type' => 'goodbye',
                'title' => 'Goodbye / Call Termination Audio',
                'description' => 'Plays before hanging up after max retries or goodbye action.',
                'path' => IvrSetting::get('goodbye_audio_path'),
                'fallback' => IvrSetting::get('goodbye_fallback_text'),
            ],
        ];

        // Enrich system slots with storage metadata
        foreach ($systemAudioSlots as &$slot) {
            $slot['url'] = !empty($slot['path']) ? $this->audioStorageService->getUrl($slot['path']) : null;
            $slot['meta'] = !empty($slot['path']) ? $this->audioStorageService->getMetadata($slot['path']) : null;
        }
        unset($slot);

        // 2. Option audio entries
        $options = IvrOption::ordered()->get();
        $optionAudioSlots = [];

        foreach ($options as $option) {
            $meta = !empty($option->audio_path) ? $this->audioStorageService->getMetadata($option->audio_path) : null;
            $optionAudioSlots[] = [
                'option' => $option,
                'target_type' => 'option',
                'title' => "Option {$option->digit}: {$option->title}",
                'path' => $option->audio_path,
                'url' => $option->resolved_audio_url,
                'meta' => $meta,
                'fallback' => $option->fallback_text,
            ];
        }

        return view('pages.admin.audio.index', [
            'title' => 'IVR Audio Management',
            'systemAudioSlots' => $systemAudioSlots,
            'optionAudioSlots' => $optionAudioSlots,
        ]);
    }

    /**
     * Upload or replace an audio file.
     */
    public function upload(AudioUploadRequest $request): RedirectResponse
    {
        $targetType = $request->input('target_type');
        $file = $request->file('audio_file');

        try {
            if ($targetType === 'option') {
                $option = IvrOption::findOrFail($request->input('option_id'));
                $upload = $this->audioStorageService->replace($file, $option->audio_path, 'audio/options');

                $option->update([
                    'audio_path' => $upload['path'],
                    'audio_url' => $upload['url'],
                ]);

                return back()->with('success', "Audio for Option {$option->digit} uploaded successfully.");
            }

            // System audio slot
            $keyMap = [
                'welcome' => 'welcome_audio_path',
                'main_presentation' => 'main_presentation_audio_path',
                'menu_prompt' => 'menu_prompt_audio_path',
                'invalid_input' => 'invalid_input_audio_path',
                'no_input' => 'no_input_audio_path',
                'goodbye' => 'goodbye_audio_path',
            ];

            $settingKey = $keyMap[$targetType] ?? null;
            if (!$settingKey) {
                return back()->with('error', 'Invalid audio target type.');
            }

            $oldPath = IvrSetting::get($settingKey);
            $upload = $this->audioStorageService->replace($file, $oldPath, 'audio/system');

            IvrSetting::set($settingKey, $upload['path'], [
                'type' => 'audio',
                'group' => 'audio',
            ]);

            return back()->with('success', 'Audio file uploaded and updated successfully.');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to upload audio: ' . $e->getMessage());
        }
    }

    /**
     * Remove an audio file and revert to fallback text.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $targetType = $request->input('target_type');

        try {
            if ($targetType === 'option') {
                $option = IvrOption::findOrFail($request->input('option_id'));
                if (!empty($option->audio_path)) {
                    $this->audioStorageService->delete($option->audio_path);
                }
                $option->update([
                    'audio_path' => null,
                    'audio_url' => null,
                ]);

                return back()->with('success', "Audio for Option {$option->digit} removed. Reverted to fallback.");
            }

            $keyMap = [
                'welcome' => 'welcome_audio_path',
                'main_presentation' => 'main_presentation_audio_path',
                'menu_prompt' => 'menu_prompt_audio_path',
                'invalid_input' => 'invalid_input_audio_path',
                'no_input' => 'no_input_audio_path',
                'goodbye' => 'goodbye_audio_path',
            ];

            $settingKey = $keyMap[$targetType] ?? null;
            if ($settingKey) {
                $oldPath = IvrSetting::get($settingKey);
                if (!empty($oldPath)) {
                    $this->audioStorageService->delete($oldPath);
                }
                IvrSetting::set($settingKey, null);
                return back()->with('success', 'Audio removed. Reverted to text-to-speech fallback.');
            }

            return back()->with('error', 'Invalid target type.');
        } catch (Exception $e) {
            return back()->with('error', 'Error removing audio: ' . $e->getMessage());
        }
    }
}
