@extends('layouts.app')

@section('title', $staffMember->exists ? 'Edit Staff' : 'Register Staff')
@section('back', route('staff.index'))

@section('content')
<div class="max-w-3xl">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="eyebrow text-amber-700">Staff management</p>
            <h1 class="mt-1 text-3xl font-semibold">{{ $staffMember->exists ? 'Edit staff record' : 'Register staff' }}</h1>
        </div>
        <a class="btn-secondary hidden shrink-0 lg:inline-flex" href="{{ route('staff.index') }}">Back</a>
    </div>
    <form class="mt-8 border border-stone-200 bg-white p-6 sm:p-8" method="POST" action="{{ $staffMember->exists ? route('staff.update', $staffMember) : route('staff.store') }}">
        @csrf @if($staffMember->exists) @method('PUT') @endif
        @include('staff.partials.form-fields')
        <div class="mt-8 flex gap-3"><button class="btn-primary">Save staff</button><a class="btn-secondary" href="{{ route('staff.index') }}">Cancel</a></div>
    </form>
</div>
@endsection
