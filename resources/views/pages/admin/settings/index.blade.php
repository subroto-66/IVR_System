@extends('layouts.app')

@section('content')
    <div class="max-w-4xl space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-2xl">
                    IVR System Configuration & Settings
                </h1>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Configure call limits, timeouts, keypad digits, SMS dispatching, and text-to-speech fallback prompts.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800">
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    Hot-Reload Telephony Config
                </span>
            </div>
        </div>

        @if (session('success'))
            <div class="flex items-center gap-3 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300">
                <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-sm font-medium text-rose-800 dark:bg-rose-950/40 dark:border-rose-800 dark:text-rose-300">
                <div class="flex items-center gap-2 mb-1.5 font-bold">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Please fix the following issues:
                </div>
                <ul class="list-disc list-inside space-y-1 text-xs font-normal">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.ivr.settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Section 1: Telephony Controls & Limits -->
            <div class="rounded-2xl border border-gray-200/80 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 shadow-xs">
                <div class="border-b border-gray-100 dark:border-gray-800 pb-4 mb-6">
                    <div class="flex items-center gap-2">
                        <span class="size-2 rounded-full bg-brand-500"></span>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Call Flow Controls & Retries</h2>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Manage caller timeouts, retry thresholds, and loop prevention.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1.5">
                            Max Retries Before Disconnect <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" name="max_retries" value="{{ old('max_retries', $settings['max_retries']) }}" min="1" max="10" required
                            class="w-full rounded-xl border border-gray-300 bg-gray-50/30 px-4 py-2.5 text-sm text-gray-900 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white transition" />
                        <span class="text-[11px] text-gray-400 mt-1 block">Max invalid inputs or timeouts allowed before playing Goodbye and hanging up.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1.5">
                            Keypad Gather Timeout (seconds) <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" name="gather_timeout" value="{{ old('gather_timeout', $settings['gather_timeout']) }}" min="2" max="30" required
                            class="w-full rounded-xl border border-gray-300 bg-gray-50/30 px-4 py-2.5 text-sm text-gray-900 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white transition" />
                        <span class="text-[11px] text-gray-400 mt-1 block">Twilio waits this many seconds for caller to press a digit before prompting.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1.5">
                            Return to Main Menu Keypad Digit <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="return_to_menu_digit" value="{{ old('return_to_menu_digit', $settings['return_to_menu_digit']) }}" maxlength="2" required
                            class="w-full rounded-xl border border-gray-300 bg-gray-50/30 px-4 py-2.5 text-sm font-mono font-bold text-gray-900 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white transition" />
                        <span class="text-[11px] text-gray-400 mt-1 block">Digit callers press to go back to the main options menu (default: 9).</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1.5">
                            Return to Main Menu Voice Prompt <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="return_to_menu_prompt_text" value="{{ old('return_to_menu_prompt_text', $settings['return_to_menu_prompt_text']) }}" required
                            class="w-full rounded-xl border border-gray-300 bg-gray-50/30 px-4 py-2.5 text-sm text-gray-900 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white transition" />
                        <span class="text-[11px] text-gray-400 mt-1 block">Spoken after option audio playback completes.</span>
                    </div>
                </div>
            </div>

            <!-- Section 2: Twilio SMS Integration -->
            <div class="rounded-2xl border border-purple-200/80 bg-purple-50/20 p-6 dark:border-purple-900/40 dark:bg-purple-950/10 shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-purple-100 dark:border-purple-900/30 pb-4 mb-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="size-2 rounded-full bg-purple-500"></span>
                            <h2 class="text-base font-bold text-purple-900 dark:text-purple-300">Twilio SMS Dispatch Feature</h2>
                        </div>
                        <p class="text-xs text-purple-700/70 dark:text-purple-300/60 mt-0.5">
                            Automatically text callers recruitment portals or information links upon keypad request.
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer select-none">
                        <input type="checkbox" name="sms_enabled" value="1" {{ $settings['sms_enabled'] ? 'checked' : '' }} class="sr-only peer" />
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-purple-600"></div>
                        <span class="ms-3 text-sm font-semibold text-gray-800 dark:text-gray-200">Enable SMS Dispatch</span>
                    </label>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1.5">
                            Default SMS Message Content <span class="text-rose-500">*</span>
                        </label>
                        <textarea name="sms_message" rows="3" required
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white transition">{{ old('sms_message', $settings['sms_message']) }}</textarea>
                        <span class="text-[11px] text-gray-400 mt-1 block">Sent to the caller's mobile number via Twilio Messaging API.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1.5">
                            Voice Confirmation Message (Spoken to caller) <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="sms_confirmation_fallback_text" value="{{ old('sms_confirmation_fallback_text', $settings['sms_confirmation_fallback_text']) }}" required
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white transition" />
                    </div>
                </div>
            </div>

            <!-- Section 3: Speech Fallback Prompts (Amazon Polly Joanna) -->
            <div class="rounded-2xl border border-gray-200/80 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 shadow-xs">
                <div class="border-b border-gray-100 dark:border-gray-800 pb-4 mb-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="size-2 rounded-full bg-brand-500"></span>
                            <h2 class="text-base font-bold text-gray-900 dark:text-white">Text-to-Speech Fallback Messages</h2>
                        </div>
                        <span class="text-[11px] font-semibold text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-950/40 px-2.5 py-0.5 rounded-full">
                            Amazon Polly Joanna
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Spoken automatically whenever an audio recording is missing or not uploaded.
                    </p>
                </div>

                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1.5">
                            Welcome Fallback Speech
                        </label>
                        <textarea name="welcome_fallback_text" rows="2"
                            class="w-full rounded-xl border border-gray-300 bg-gray-50/30 px-4 py-2.5 text-sm text-gray-900 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white transition">{{ old('welcome_fallback_text', $settings['welcome_fallback_text']) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1.5">
                            Main Presentation Fallback Speech
                        </label>
                        <textarea name="main_presentation_fallback_text" rows="3"
                            class="w-full rounded-xl border border-gray-300 bg-gray-50/30 px-4 py-2.5 text-sm text-gray-900 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white transition">{{ old('main_presentation_fallback_text', $settings['main_presentation_fallback_text']) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1.5">
                            Custom Menu Speech Instructions (Optional)
                        </label>
                        <textarea name="menu_fallback_text" rows="2" placeholder="Leave empty to dynamically speak active options (e.g. 'Press 1 for Company Info...')"
                            class="w-full rounded-xl border border-gray-300 bg-gray-50/30 px-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white transition">{{ old('menu_fallback_text', $settings['menu_fallback_text']) }}</textarea>
                        <span class="text-[11px] text-gray-400 mt-1 block">Leaving this blank allows the system to auto-build menu instructions directly from active database options.</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1.5">
                                Invalid Selection Voice Prompt
                            </label>
                            <input type="text" name="invalid_fallback_text" value="{{ old('invalid_fallback_text', $settings['invalid_fallback_text']) }}"
                                class="w-full rounded-xl border border-gray-300 bg-gray-50/30 px-4 py-2.5 text-sm text-gray-900 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white transition" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1.5">
                                No-Input Timeout Voice Prompt
                            </label>
                            <input type="text" name="no_input_fallback_text" value="{{ old('no_input_fallback_text', $settings['no_input_fallback_text']) }}"
                                class="w-full rounded-xl border border-gray-300 bg-gray-50/30 px-4 py-2.5 text-sm text-gray-900 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white transition" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1.5">
                            Goodbye & Disconnect Voice Prompt
                        </label>
                        <input type="text" name="goodbye_fallback_text" value="{{ old('goodbye_fallback_text', $settings['goodbye_fallback_text']) }}"
                            class="w-full rounded-xl border border-gray-300 bg-gray-50/30 px-4 py-2.5 text-sm text-gray-900 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white transition" />
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 text-xs font-semibold text-white bg-brand-500 rounded-xl hover:bg-brand-600 shadow-sm transition">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Save System Settings
                </button>
            </div>
        </form>
    </div>
@endsection
