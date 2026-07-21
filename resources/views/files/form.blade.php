@extends('layouts.app')

@section('title', $file->exists ? 'Edit File' : 'Register File')

@section('content')
<div class="max-w-5xl">
    <p class="eyebrow text-amber-700">File registry</p><h1 class="mt-1 text-3xl font-semibold">{{ $file->exists ? 'Edit physical file' : 'Register physical file' }}</h1>
    <form class="mt-8 space-y-8" method="POST" action="{{ $file->exists ? route('files.update', $file) : route('files.store') }}">@csrf @if($file->exists) @method('PUT') @endif
        <section class="border border-stone-200 bg-white p-6"><h2 class="text-xl font-semibold">General information</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label><span class="form-label">Reference number</span><input class="form-input" name="reference_number" value="{{ old('reference_number', $file->reference_number) }}" required autofocus></label>
                <label><span class="form-label">Loan reference</span><input class="form-input" name="loan_reference" value="{{ old('loan_reference', $file->loan_reference) }}"></label>
                <label><span class="form-label">Purchaser</span><input class="form-input" name="purchaser" value="{{ old('purchaser', $file->purchaser) }}" required></label>
                <label><span class="form-label">Vendor</span><input class="form-input" name="vendor" value="{{ old('vendor', $file->vendor) }}"></label>
            </div>
        </section>
        <section class="border border-stone-200 bg-white p-6"><h2 class="text-xl font-semibold">Property and matter</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label class="sm:col-span-2"><span class="form-label">Property</span><textarea class="form-input min-h-24" name="property" required>{{ old('property', $file->property) }}</textarea></label>
                <label><span class="form-label">Matter type</span><input class="form-input" name="matter_type" value="{{ old('matter_type', $file->matter_type) }}"></label>
                <label><span class="form-label">Important date</span><input class="form-input" type="date" name="important_date" value="{{ old('important_date', $file->important_date?->format('Y-m-d')) }}"></label>
            </div>
        </section>
        <section class="border border-stone-200 bg-white p-6"><h2 class="text-xl font-semibold">Responsibility and storage</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label><span class="form-label">Person in charge</span><select class="form-input" name="person_in_charge_id"><option value="">Unassigned</option>@foreach($staff as $member)<option value="{{ $member->id }}" @selected(old('person_in_charge_id', $file->person_in_charge_id) == $member->id)>{{ $member->full_name }}</option>@endforeach</select></label>
                <label><span class="form-label">Shelf location</span><select class="form-input" name="shelf_id" required><option value="">Select location</option>@foreach($shelves as $shelf)<option value="{{ $shelf->id }}" @selected(old('shelf_id', $file->shelf_id) == $shelf->id)>{{ $shelf->full_location }}</option>@endforeach</select></label>
            </div>
        </section>
        <div class="flex gap-3"><button class="btn-primary">{{ $file->exists ? 'Save changes' : 'Save and generate identity' }}</button><a class="btn-secondary" href="{{ $file->exists ? route('files.show', $file) : route('files.index') }}">Cancel</a></div>
    </form>
</div>
@endsection
