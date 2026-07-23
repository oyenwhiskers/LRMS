<div class="auth-form-inner">
    @if(($authMode ?? 'login') === 'login' && $errors->any())
        <div class="mb-6 border-l-4 border-red-700 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            <p class="font-semibold">Please correct the following:</p>
            <ul class="mt-1 list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="eyebrow text-amber-700">Welcome back</p>
    <h2 class="auth-form-title">Sign in to TSANG &amp; CO</h2>
    <div class="auth-form-rule" aria-hidden="true"></div>

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <label class="form-label" for="login_email">Email address</label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25H4.5a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5H4.5a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                    </svg>
                </span>
                <input
                    class="form-input auth-input"
                    id="login_email"
                    name="email"
                    type="email"
                    value="{{ ($authMode ?? 'login') === 'login' ? old('email') : '' }}"
                    placeholder="Enter your email"
                    required
                    @if(($authMode ?? 'login') === 'login') autofocus @endif
                    autocomplete="email"
                >
            </div>
        </div>

        <div>
            <label class="form-label" for="login_password">Password</label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V7.5a4.5 4.5 0 10-9 0v3m-.75 0h10.5A1.75 1.75 0 0118.5 12.25v6A1.75 1.75 0 0116.75 20H7.25A1.75 1.75 0 015.5 18.25v-6a1.75 1.75 0 011.75-1.75z"/>
                    </svg>
                </span>
                <input
                    class="form-input auth-input-with-action"
                    id="login_password"
                    name="password"
                    type="password"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                >
                <button
                    class="auth-input-action"
                    type="button"
                    data-password-toggle="login_password"
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

        <label class="flex items-center gap-3 text-sm text-stone-600">
            <input class="size-4 rounded border-stone-300 text-amber-700 focus:ring-amber-600" type="checkbox" name="remember" value="1">
            Keep me signed in
        </label>

        <button class="auth-submit" type="submit">
            Sign in securely
            <span aria-hidden="true">→</span>
        </button>
    </form>

    <div class="auth-divider">Or</div>

    <p class="text-center text-sm text-stone-600">
        New staff member?
        <a
            class="font-semibold text-amber-800 hover:text-amber-700"
            href="{{ route('register') }}"
            data-auth-switch="register"
        >Request an account →</a>
    </p>
</div>
