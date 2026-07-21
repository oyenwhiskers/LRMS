@extends('layouts.app')

@section('title', 'Request access')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="mb-8">
        <p class="eyebrow text-amber-700">Staff registration</p>
        <h1 class="mt-2 text-3xl font-semibold">Request LRMS access</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-stone-600">Use your assigned staff number and select the position you are requesting. Your submission will be reviewed before access is granted.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="border border-stone-200 bg-white p-6 shadow-lg shadow-stone-900/5 sm:p-8">
        @csrf
        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label class="form-label" for="staff_number">Staff number</label>
                <input class="form-input" id="staff_number" name="staff_number" value="{{ old('staff_number') }}" required autofocus autocomplete="off">
                <p class="form-help">Enter the number exactly as issued.</p>
            </div>
            <div>
                <label class="form-label" for="requested_position_id">Requested position</label>
                <select class="form-input" id="requested_position_id" name="requested_position_id" required>
                    <option value="">Select a position</option>
                    @foreach($positions as $position)
                        <option value="{{ $position->id }}" @selected(old('requested_position_id') == $position->id)>{{ $position->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="form-label" for="email">Work email address</label>
                <input class="form-input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
            </div>
            <div>
                <label class="form-label" for="password">Password</label>
                <input class="form-input" id="password" name="password" type="password" required autocomplete="new-password">
                <p class="form-help">At least 12 characters with upper/lowercase, a number, and a symbol.</p>
            </div>
            <div>
                <label class="form-label" for="password_confirmation">Confirm password</label>
                <input class="form-input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
            </div>
        </div>

        <div class="mt-8 flex flex-col-reverse gap-3 border-t border-stone-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
            <a class="text-center text-sm font-semibold text-stone-600 hover:text-stone-900" href="{{ route('login') }}">Return to sign in</a>
            <button class="btn-primary" type="submit">Submit for review</button>
        </div>
    </form>
</div>
@endsection
