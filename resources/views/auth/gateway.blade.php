@extends('layouts.app')

@section('title', ($authMode ?? 'login') === 'register' ? 'Request access' : 'Sign in')

@section('auth_fullscreen')
<div
    class="auth-shell"
    data-auth-gateway
    data-auth-mode="{{ $authMode ?? 'login' }}"
    data-login-url="{{ route('login') }}"
    data-register-url="{{ route('register') }}"
    data-login-title="Sign in · {{ config('app.name') }}"
    data-register-title="Request access · {{ config('app.name') }}"
>
    @include('auth.partials.brand-panel', ['authMode' => $authMode ?? 'login'])

    <section class="auth-form">
        <div class="auth-panel-stack">
            <div
                class="auth-panel {{ ($authMode ?? 'login') === 'login' ? 'is-active' : '' }}"
                data-auth-panel="login"
                @if(($authMode ?? 'login') !== 'login') hidden @endif
            >
                @include('auth.partials.login-panel')
            </div>

            <div
                class="auth-panel {{ ($authMode ?? 'login') === 'register' ? 'is-active' : '' }}"
                data-auth-panel="register"
                @if(($authMode ?? 'login') !== 'register') hidden @endif
            >
                @include('auth.partials.register-panel')
            </div>
        </div>
    </section>
</div>
@endsection
