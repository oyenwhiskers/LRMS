<div class="mx-auto w-full max-w-xl">
    @if(($authMode ?? 'login') === 'register' && $errors->any())
        <div class="mb-6 border-l-4 border-red-700 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            <p class="font-semibold">Please correct the following:</p>
            <ul class="mt-1 list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="eyebrow text-amber-700">Join the workspace</p>
    <h2 class="auth-form-title">Request Access</h2>
    <div class="auth-form-rule" aria-hidden="true"></div>

    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="form-label" for="staff_number">Staff number</label>
                <div class="auth-input-wrap">
                    <span class="auth-input-icon" aria-hidden="true">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                        </svg>
                    </span>
                    <input
                        class="form-input auth-input"
                        id="staff_number"
                        name="staff_number"
                        value="{{ ($authMode ?? 'login') === 'register' ? old('staff_number') : '' }}"
                        placeholder="Enter staff number"
                        required
                        @if(($authMode ?? 'login') === 'register') autofocus @endif
                        autocomplete="off"
                    >
                </div>
                <p class="form-help">Enter the number exactly as issued.</p>
            </div>

            <div>
                <label class="form-label" for="requested_position_id">Requested position</label>
                <div class="auth-input-wrap">
                    <span class="auth-input-icon" aria-hidden="true">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25a2.25 2.25 0 01-2.25 2.25h-12a2.25 2.25 0 01-2.25-2.25v-4.25m16.5 0a2.25 2.25 0 00-2.25-2.25h-12a2.25 2.25 0 00-2.25 2.25m16.5 0V9.75A2.25 2.25 0 0018 7.5h-3.75m-9 6.65V9.75A2.25 2.25 0 016 7.5h3.75m0 0V6A2.25 2.25 0 0112 3.75h0A2.25 2.25 0 0114.25 6v1.5"/>
                        </svg>
                    </span>
                    <select class="form-input auth-input" id="requested_position_id" name="requested_position_id" required>
                        <option value="">Select a position</option>
                        @foreach($positions as $position)
                            <option
                                value="{{ $position->id }}"
                                @selected(($authMode ?? 'login') === 'register' && old('requested_position_id') == $position->id)
                            >{{ $position->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div>
            <label class="form-label" for="register_email">Work email address</label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25H4.5a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5H4.5a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                    </svg>
                </span>
                <input
                    class="form-input auth-input"
                    id="register_email"
                    name="email"
                    type="email"
                    value="{{ ($authMode ?? 'login') === 'register' ? old('email') : '' }}"
                    placeholder="Enter your work email"
                    required
                    autocomplete="email"
                >
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="form-label" for="register_password">Password</label>
                <div class="auth-input-wrap">
                    <span class="auth-input-icon" aria-hidden="true">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V7.5a4.5 4.5 0 10-9 0v3m-.75 0h10.5A1.75 1.75 0 0118.5 12.25v6A1.75 1.75 0 0116.75 20H7.25A1.75 1.75 0 015.5 18.25v-6a1.75 1.75 0 011.75-1.75z"/>
                        </svg>
                    </span>
                    <input
                        class="form-input auth-input-with-action"
                        id="register_password"
                        name="password"
                        type="password"
                        placeholder="Create a password"
                        required
                        autocomplete="new-password"
                    >
                    <button
                        class="auth-input-action"
                        type="button"
                        data-password-toggle="register_password"
                        aria-label="Show password"
                        aria-pressed="false"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </button>
                </div>
                <p class="form-help">At least 12 characters with upper/lowercase, a number, and a symbol.</p>
            </div>

            <div>
                <label class="form-label" for="register_password_confirmation">Confirm password</label>
                <div class="auth-input-wrap">
                    <span class="auth-input-icon" aria-hidden="true">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V7.5a4.5 4.5 0 10-9 0v3m-.75 0h10.5A1.75 1.75 0 0118.5 12.25v6A1.75 1.75 0 0116.75 20H7.25A1.75 1.75 0 015.5 18.25v-6a1.75 1.75 0 011.75-1.75z"/>
                        </svg>
                    </span>
                    <input
                        class="form-input auth-input-with-action"
                        id="register_password_confirmation"
                        name="password_confirmation"
                        type="password"
                        placeholder="Confirm your password"
                        required
                        autocomplete="new-password"
                    >
                    <button
                        class="auth-input-action"
                        type="button"
                        data-password-toggle="register_password_confirmation"
                        aria-label="Show password"
                        aria-pressed="false"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <button class="auth-submit" type="submit">
            Submit for review
            <span aria-hidden="true">→</span>
        </button>
    </form>

    <div class="auth-divider">Or</div>

    <p class="text-center text-sm text-stone-600">
        Already registered?
        <a
            class="font-semibold text-amber-800 hover:text-amber-700"
            href="{{ route('login') }}"
            data-auth-switch="login"
        >Return to sign in →</a>
    </p>
</div>
