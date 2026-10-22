@extends('layouts.app')

@section('title', 'Change password')
@section('back', url('/'))

@section('content')
<div class="mx-auto max-w-lg">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="eyebrow text-amber-700">Account</p>
            <h1 class="mt-1 text-3xl font-semibold">Change password</h1>
            <p class="mt-2 text-sm text-stone-600">Use a strong password with at least 12 characters, mixed case, a number, and a symbol.</p>
        </div>
        <a class="btn-secondary hidden shrink-0 lg:inline-flex" href="{{ url('/') }}">Back</a>
    </div>

    <form class="mt-8 grid gap-5 border border-stone-200 bg-white p-6 sm:p-8" method="POST" action="{{ route('account.password.update') }}">
        @csrf
        @method('PUT')

        <div>
            <label class="form-label" for="current_password">Current password</label>
            <div class="relative mt-1.5">
                <input
                    class="form-input pr-12"
                    id="current_password"
                    name="current_password"
                    type="password"
                    required
                    autocomplete="current-password"
                    autofocus
                >
                <button
                    class="absolute inset-y-0 right-0 px-3 text-stone-500"
                    type="button"
                    data-password-toggle="current_password"
                    aria-label="Show password"
                    aria-pressed="false"
                >Show</button>
            </div>
            @error('current_password')
                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="form-label" for="password">New password</label>
            <div class="relative mt-1.5">
                <input
                    class="form-input pr-12"
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="new-password"
                >
                <button
                    class="absolute inset-y-0 right-0 px-3 text-stone-500"
                    type="button"
                    data-password-toggle="password"
                    aria-label="Show password"
                    aria-pressed="false"
                >Show</button>
            </div>
            @error('password')
                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="form-label" for="password_confirmation">Confirm new password</label>
            <div class="relative mt-1.5">
                <input
                    class="form-input pr-12"
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                >
                <button
                    class="absolute inset-y-0 right-0 px-3 text-stone-500"
                    type="button"
                    data-password-toggle="password_confirmation"
                    aria-label="Show password"
                    aria-pressed="false"
                >Show</button>
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-3 pt-2">
            <a class="btn-secondary" href="{{ url('/') }}">Cancel</a>
            <button class="btn-primary" type="submit">Update password</button>
        </div>
    </form>
</div>
@endsection
