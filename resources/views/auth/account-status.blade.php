@extends('layouts.app')

@section('title', 'Account status')

@section('content')
<div class="mx-auto max-w-2xl border border-stone-200 bg-white p-8 text-center shadow-lg shadow-stone-900/5 sm:p-12">
    @if(auth()->user()->status === App\Models\User::STATUS_PENDING)
        <div class="mx-auto grid size-14 place-items-center rounded-full bg-amber-100 text-amber-800">
            <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.5" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p class="eyebrow mt-6 text-amber-700">Review in progress</p>
        <h1 class="mt-2 text-3xl font-semibold">Your request is pending</h1>
        <p class="mx-auto mt-4 max-w-lg text-sm leading-6 text-stone-600">An administrator must verify your staff record and position before you can access LRMS. You may sign out and return later.</p>
    @elseif(auth()->user()->status === App\Models\User::STATUS_REJECTED)
        <div class="mx-auto grid size-14 place-items-center rounded-full bg-red-100 text-red-800">
            <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/></svg>
        </div>
        <p class="eyebrow mt-6 text-red-700">Review completed</p>
        <h1 class="mt-2 text-3xl font-semibold">Your request was not approved</h1>
        <p class="mx-auto mt-4 max-w-lg text-sm leading-6 text-stone-600">{{ auth()->user()->rejection_reason ?: 'Please contact an LRMS administrator for assistance.' }}</p>
    @else
        <p class="eyebrow text-emerald-700">Access approved</p>
        <h1 class="mt-2 text-3xl font-semibold">Your account is active</h1>
        <a class="btn-primary mt-6 inline-flex" href="{{ route('dashboard') }}">Continue to dashboard</a>
    @endif

    @if(auth()->user()->status !== App\Models\User::STATUS_APPROVED)
        <form class="mt-8" method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn-secondary" type="submit">Sign out</button>
        </form>
    @endif
</div>
@endsection
