@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
<div class="mx-auto grid max-w-5xl overflow-hidden border border-stone-200 bg-white shadow-xl shadow-stone-900/5 lg:grid-cols-5">
    <section class="bg-neutral-950 p-8 text-stone-100 lg:col-span-2 lg:p-10">
        <p class="eyebrow">Secure access</p>
        <h1 class="mt-4 text-3xl font-semibold leading-tight">Legal work, managed with clarity.</h1>
        <p class="mt-4 text-sm leading-6 text-stone-400">Sign in to access approved LRMS resources, workflows, and administrative controls.</p>
        <div class="mt-10 border-t border-amber-300/20 pt-6 text-xs leading-5 text-stone-500">
            Access is restricted to approved personnel. Activity may be audited.
        </div>
    </section>

    <section class="p-8 lg:col-span-3 lg:p-12">
        <p class="eyebrow text-amber-700">Welcome back</p>
        <h2 class="mt-2 text-2xl font-semibold">Sign in to LRMS</h2>

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
            @csrf
            <div>
                <label class="form-label" for="email">Email address</label>
                <input class="form-input" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            </div>
            <div>
                <label class="form-label" for="password">Password</label>
                <input class="form-input" id="password" name="password" type="password" required autocomplete="current-password">
            </div>
            <label class="flex items-center gap-3 text-sm text-stone-600">
                <input class="size-4 rounded border-stone-300 text-amber-700 focus:ring-amber-600" type="checkbox" name="remember" value="1">
                Keep me signed in
            </label>
            <button class="btn-primary w-full" type="submit">Sign in securely</button>
        </form>

        <p class="mt-6 text-center text-sm text-stone-600">New staff member? <a class="font-semibold text-amber-800 hover:text-amber-700" href="{{ route('register') }}">Request an account</a></p>
    </section>
</div>
@endsection
