@extends('layouts.app')

@section('title', $staffMember->exists ? 'Edit Staff' : 'Register Staff')

@section('content')
<div class="max-w-3xl">
    <p class="eyebrow text-amber-700">Staff management</p>
    <h1 class="mt-1 text-3xl font-semibold">{{ $staffMember->exists ? 'Edit staff record' : 'Register staff' }}</h1>
    <form class="mt-8 border border-stone-200 bg-white p-6 sm:p-8" method="POST" action="{{ $staffMember->exists ? route('staff.update', $staffMember) : route('staff.store') }}">
        @csrf @if($staffMember->exists) @method('PUT') @endif
        <div class="grid gap-6 sm:grid-cols-2">
            <label><span class="form-label">Staff number</span><input class="form-input" name="staff_number" value="{{ old('staff_number', $staffMember->staff_number) }}" required autofocus></label>
            <label><span class="form-label">Full name</span><input class="form-input" name="full_name" value="{{ old('full_name', $staffMember->full_name) }}" required></label>
            <label><span class="form-label">Email</span><input class="form-input" type="email" name="email" value="{{ old('email', $staffMember->email) }}"></label>
            <label><span class="form-label">Phone</span><input class="form-input" name="phone" value="{{ old('phone', $staffMember->phone) }}"></label>
            <label class="sm:col-span-2"><span class="form-label">Job position</span>
                <select class="form-input" name="position_id"><option value="">Unassigned</option>
                    @foreach($positions as $position)<option value="{{ $position->id }}" @selected(old('position_id', $staffMember->position_id) == $position->id)>{{ $position->name }}{{ $position->is_active ? '' : ' (Inactive)' }}</option>@endforeach
                </select>
            </label>
        </div>
        <div class="mt-8 flex gap-3"><button class="btn-primary">Save staff</button><a class="btn-secondary" href="{{ route('staff.index') }}">Cancel</a></div>
    </form>
</div>
@endsection
