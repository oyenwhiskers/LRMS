@extends('layouts.app')

@section('title', $staffMember->exists ? 'Edit Staff' : 'Register Staff')

@section('content')
<div class="max-w-3xl">
    <p class="eyebrow text-amber-700">Staff management</p>
    <h1 class="mt-1 text-3xl font-semibold">{{ $staffMember->exists ? 'Edit staff record' : 'Register staff' }}</h1>
    <form class="mt-8 border border-stone-200 bg-white p-6 sm:p-8" method="POST" action="{{ $staffMember->exists ? route('staff.update', $staffMember) : route('staff.store') }}">
        @csrf @if($staffMember->exists) @method('PUT') @endif
        @include('staff.partials.form-fields')
        <div class="mt-8 flex gap-3"><button class="btn-primary">Save staff</button><a class="btn-secondary" href="{{ route('staff.index') }}">Cancel</a></div>
    </form>
</div>
@endsection
