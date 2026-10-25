@extends('layouts.app')

@section('title', $file->exists ? 'Edit Case' : 'Register Case')
@section('back', route('files.index'))

@php
    $selectedType = old('case_type', $file->case_type ?: App\Models\LegalFile::TYPE_SNP);
    $isSnpSelected = App\Models\LegalFile::isSnpType($selectedType);
@endphp

@section('content')
<div>
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="eyebrow text-amber-700">Case register</p>
            <h1 class="mt-1 text-3xl font-semibold">{{ $file->exists ? 'Edit case' : 'Register case' }}</h1>
        </div>
        <a class="btn-secondary hidden shrink-0 lg:inline-flex" href="{{ route('files.index') }}">Back</a>
    </div>

    <form
        class="mt-8 space-y-8"
        method="POST"
        action="{{ $file->exists ? route('files.update', $file) : route('files.store') }}"
        data-case-type-form
    >
        @csrf
        @if($file->exists)
            @method('PUT')
        @endif

        <section class="border border-stone-200 bg-white p-6">
            <h2 class="text-xl font-semibold">Case information</h2>

            <div class="mt-5 grid gap-5">
                @if($file->exists)
                    <div class="form-row">
                        <span class="form-label form-row__label">Reference number</span>
                        <span class="form-row__control">
                            <input class="form-input bg-stone-50" value="{{ $file->reference_number }}" readonly>
                            <span class="form-help">Auto-generated and locked after registration.</span>
                        </span>
                    </div>

                    <div class="form-row">
                        <span class="form-label form-row__label">Case type</span>
                        <span class="form-row__control">
                            <input class="form-input bg-stone-50" value="{{ $file->caseTypeLabel() }}" readonly>
                        </span>
                    </div>

                    <div class="form-row">
                        <span class="form-label form-row__label">Case category</span>
                        <span class="form-row__control">
                            <input class="form-input bg-stone-50" value="{{ $file->caseCategory?->code }} — {{ $file->caseCategory?->name ?? $file->matter_type }}" readonly>
                        </span>
                    </div>
                @else
                    <label class="form-row">
                        <span class="form-label form-row__label">Case type</span>
                        <span class="form-row__control">
                            <select class="form-input" name="case_type" required autofocus data-case-type-select>
                                <option value="">Select case type</option>
                                @foreach (App\Models\LegalFile::caseTypes() as $type)
                                    <option
                                        value="{{ $type }}"
                                        data-case-type-family="{{ App\Models\LegalFile::formPanel($type) }}"
                                        @selected($selectedType === $type)
                                    >{{ App\Models\LegalFile::caseTypeLabelFor($type) }}</option>
                                @endforeach
                            </select>
                            <span class="form-help">Controls which matter fields are required.</span>
                        </span>
                    </label>

                    <label class="form-row">
                        <span class="form-label form-row__label">Case category</span>
                        <span class="form-row__control">
                            <select class="form-input" name="case_category_id" required>
                                <option value="">Select category</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('case_category_id') === (string) $category->id)>
                                        {{ $category->code }} — {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="form-help">Running code only. File number will be generated as CODE/YEAR/NUMBER.</span>
                        </span>
                    </label>
                @endif

                {{-- Loan fields --}}
                <div class="grid gap-5 {{ $selectedType === App\Models\LegalFile::TYPE_LOAN ? '' : 'hidden' }}" data-case-type-panel="loan">
                    <label class="form-row">
                        <span class="form-label form-row__label">Borrower</span>
                        <span class="form-row__control">
                            <input class="form-input" name="purchaser" value="{{ old('purchaser', $file->purchaser) }}" data-case-type-required @required($selectedType === App\Models\LegalFile::TYPE_LOAN) @disabled($selectedType !== App\Models\LegalFile::TYPE_LOAN)>
                        </span>
                    </label>

                    <label class="form-row">
                        <span class="form-label form-row__label">Bank</span>
                        <span class="form-row__control">
                            <input class="form-input" name="bank" value="{{ old('bank', $file->bank) }}" data-case-type-required @required($selectedType === App\Models\LegalFile::TYPE_LOAN) @disabled($selectedType !== App\Models\LegalFile::TYPE_LOAN)>
                        </span>
                    </label>

                    <label class="form-row form-row--textarea">
                        <span class="form-label form-row__label">Property address</span>
                        <span class="form-row__control">
                            <textarea class="form-input min-h-24" name="property" data-case-type-required @required($selectedType === App\Models\LegalFile::TYPE_LOAN) @disabled($selectedType !== App\Models\LegalFile::TYPE_LOAN)>{{ old('property', $file->property) }}</textarea>
                        </span>
                    </label>

                    <label class="form-row">
                        <span class="form-label form-row__label">Title number</span>
                        <span class="form-row__control">
                            <input class="form-input" name="title_number" value="{{ old('title_number', $file->title_number) }}" data-case-type-required @required($selectedType === App\Models\LegalFile::TYPE_LOAN) @disabled($selectedType !== App\Models\LegalFile::TYPE_LOAN)>
                        </span>
                    </label>
                </div>

                {{-- S&P-shaped fields (sales, MOT, DOA, gift, POA, etc.) --}}
                <div class="grid gap-5 {{ $isSnpSelected ? '' : 'hidden' }}" data-case-type-panel="snp">
                    <label class="form-row">
                        <span class="form-label form-row__label">Purchaser</span>
                        <span class="form-row__control">
                            <input class="form-input" name="purchaser" value="{{ old('purchaser', $file->purchaser) }}" data-case-type-required @required($isSnpSelected) @disabled(! $isSnpSelected)>
                        </span>
                    </label>

                    <label class="form-row">
                        <span class="form-label form-row__label">Vendor</span>
                        <span class="form-row__control">
                            <input class="form-input" name="vendor" value="{{ old('vendor', $file->vendor) }}" @disabled(! $isSnpSelected)>
                        </span>
                    </label>

                    <label class="form-row form-row--textarea">
                        <span class="form-label form-row__label">Property address</span>
                        <span class="form-row__control">
                            <textarea class="form-input min-h-24" name="property" data-case-type-required @required($isSnpSelected) @disabled(! $isSnpSelected)>{{ old('property', $file->property) }}</textarea>
                        </span>
                    </label>

                    <label class="form-row">
                        <span class="form-label form-row__label">Title number</span>
                        <span class="form-row__control">
                            <input class="form-input" name="title_number" value="{{ old('title_number', $file->title_number) }}" data-case-type-required @required($isSnpSelected) @disabled(! $isSnpSelected)>
                            <span class="form-help">If there is no subsidiary title, enter the master title.</span>
                        </span>
                    </label>
                </div>

                {{-- Litigation fields --}}
                <div class="grid gap-5 {{ $selectedType === App\Models\LegalFile::TYPE_LITIGATION ? '' : 'hidden' }}" data-case-type-panel="litigation">
                    <label class="form-row">
                        <span class="form-label form-row__label">Suite number</span>
                        <span class="form-row__control">
                            <input class="form-input" name="suite_number" value="{{ old('suite_number', $file->suite_number) }}" data-case-type-required @required($selectedType === App\Models\LegalFile::TYPE_LITIGATION) @disabled($selectedType !== App\Models\LegalFile::TYPE_LITIGATION)>
                        </span>
                    </label>

                    <label class="form-row">
                        <span class="form-label form-row__label">Person's name</span>
                        <span class="form-row__control">
                            <input class="form-input" name="purchaser" value="{{ old('purchaser', $file->purchaser) }}" data-case-type-required @required($selectedType === App\Models\LegalFile::TYPE_LITIGATION) @disabled($selectedType !== App\Models\LegalFile::TYPE_LITIGATION)>
                        </span>
                    </label>

                    <label class="form-row">
                        <span class="form-label form-row__label">IC</span>
                        <span class="form-row__control">
                            <input class="form-input" name="ic_number" value="{{ old('ic_number', $file->ic_number) }}" data-case-type-required @required($selectedType === App\Models\LegalFile::TYPE_LITIGATION) @disabled($selectedType !== App\Models\LegalFile::TYPE_LITIGATION)>
                        </span>
                    </label>
                </div>
            </div>
        </section>

        <section class="border border-stone-200 bg-white p-6">
            <h2 class="text-xl font-semibold">Matter</h2>

            <div class="mt-5 grid gap-5">
                <label class="form-row">
                    <span class="form-label form-row__label">Start date</span>
                    <span class="form-row__control">
                        <input class="form-input" type="date" name="start_date" value="{{ old('start_date', $file->start_date?->format('Y-m-d')) }}">
                    </span>
                </label>

                <label class="form-row">
                    <span class="form-label form-row__label">End date</span>
                    <span class="form-row__control">
                        <input class="form-input" type="date" name="end_date" value="{{ old('end_date', $file->end_date?->format('Y-m-d')) }}">
                    </span>
                </label>
            </div>
        </section>

        <section
            class="border border-stone-200 bg-white p-6"
            data-related-files
            data-linkable-files='@json($linkableFilesJson)'
        >
            <h2 class="text-xl font-semibold">Related files</h2>
            <p class="mt-1 text-sm text-stone-500">Link other files that belong to the same matter or property.</p>

            <div class="mt-5 grid gap-5">
                <div class="form-row">
                    <span class="form-label form-row__label">Find file</span>
                    <span class="form-row__control">
                        <div class="related-files-search">
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <input
                                    class="form-input min-w-0 flex-1"
                                    type="search"
                                    placeholder="Search by reference or purchaser"
                                    autocomplete="off"
                                    data-related-search
                                >
                                <button class="btn-primary shrink-0" type="button" data-related-add>Add</button>
                            </div>
                            <ul class="related-files-suggestions hidden" data-related-suggestions role="listbox" hidden></ul>
                            <p class="form-help mt-2" data-related-feedback></p>
                        </div>
                    </span>
                </div>

                <div class="form-row form-row--textarea">
                    <span class="form-label form-row__label">Linked files</span>
                    <span class="form-row__control">
                        <ul class="related-files-list" data-related-list>
                            @foreach($selectedRelatedFiles as $related)
                                <li class="related-files-list__item" data-related-id="{{ $related->id }}">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-stone-800">{{ $related->reference_number }}</p>
                                        <p class="truncate text-sm text-stone-500">{{ $related->purchaser }}</p>
                                    </div>
                                    <button class="btn-secondary" type="button" data-related-remove>Remove</button>
                                    <input type="hidden" name="related_file_ids[]" value="{{ $related->id }}">
                                </li>
                            @endforeach
                        </ul>
                        <p class="related-files-empty mt-1 text-sm text-stone-500 {{ $selectedRelatedFiles->isNotEmpty() ? 'hidden' : '' }}" data-related-empty>
                            No related files linked yet.
                        </p>
                    </span>
                </div>
            </div>
        </section>

        <section class="border border-stone-200 bg-white p-6">
            <h2 class="text-xl font-semibold">Responsibility</h2>

            <div class="mt-5 grid gap-5">
                <label class="form-row">
                    <span class="form-label form-row__label">Person responsible</span>
                    <span class="form-row__control">
                        <select class="form-input" name="person_in_charge_id">
                            <option value="">Unassigned</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" @selected(old('person_in_charge_id', $file->person_in_charge_id) == $member->id)>{{ $member->full_name }}</option>
                            @endforeach
                        </select>
                    </span>
                </label>

                <div class="rounded-md border border-dashed border-stone-300 bg-stone-50 px-4 py-4 text-sm text-stone-600">
                    Shelf location will be added later when the file is stored.
                </div>
            </div>
        </section>

        <div class="flex gap-3">
            <a class="btn-secondary flex-1" href="{{ $file->exists ? route('files.show', $file) : route('files.index') }}">Cancel</a>
            <button class="btn-primary flex-1">Save</button>
        </div>
    </form>
</div>
@endsection
