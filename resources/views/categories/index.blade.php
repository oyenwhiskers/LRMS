@extends('layouts.app')

@section('title', 'Case Categories')
@section('list_layout')
@endsection

@section('content')
<div class="list-page">
    <div class="list-page__header hidden items-end justify-between gap-4 lg:flex">
        <div>
            <p class="eyebrow text-amber-700">Master data</p>
            <h1 class="mt-1 text-3xl font-semibold">Case categories</h1>
            <p class="mt-2 text-sm text-stone-500">
                Define practice areas and short codes used for auto file numbers (e.g. CON/2026/0001).
            </p>
        </div>
        @can('categories.manage')
            <button class="btn-icon-primary shrink-0" type="button" data-modal-open="add-category" aria-label="Add category" title="Add category">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                </svg>
            </button>
        @endcan
    </div>

    <form class="list-page__filters grid gap-3 border border-stone-200 bg-white p-4 sm:grid-cols-[1fr_180px]" method="GET" data-list-filter-form>
        <input
            class="form-input"
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search code or name"
            data-list-filter-search
        >
        <select class="form-input" name="status" data-list-filter-select>
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
    </form>

    @if ($errors->has('category') || $errors->has('code'))
        <div class="list-page__filters border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            {{ $errors->first('category') ?: $errors->first('code') }}
        </div>
    @endif

    {{-- Desktop table --}}
    <div @class([
        'list-page__table hidden',
        'lg:block' => $categories->isNotEmpty(),
        'list-page__table--empty lg:flex' => $categories->isEmpty(),
    ])>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th class="text-center">Files</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            @if($categories->isNotEmpty())
                <tbody>
                    @foreach($categories as $category)
                        <tr>
                            <td class="font-semibold">{{ $category->code }}</td>
                            <td>{{ $category->name }}</td>
                            <td class="text-center">{{ $category->legal_files_count }}</td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('categories.toggle', $category) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="status-badge {{ $category->is_active ? 'status-approved' : 'status-rejected' }}">
                                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </form>
                            </td>
                            <td>
                                @can('categories.manage')
                                    <div class="flex flex-wrap items-center justify-center gap-2">
                                        <button
                                            class="btn-secondary"
                                            type="button"
                                            data-modal-open="edit-category-{{ $category->id }}"
                                        >Edit</button>
                                        <button
                                            class="btn-danger"
                                            type="button"
                                            data-modal-open="{{ $category->legal_files_count > 0 ? 'cannot-delete-category-'.$category->id : 'delete-category-'.$category->id }}"
                                        >Delete</button>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            @endif
        </table>
        @if($categories->isEmpty())
            @include('layouts.partials.table-empty', [
                'icon' => 'files',
                'title' => 'No case categories configured',
                'message' => 'Add categories so new files can generate numbered references by practice area.',
            ])
        @endif
    </div>

    {{-- Mobile cards --}}
    <div @class(['list-page__mobile lg:hidden', 'list-page__mobile--empty' => $categories->isEmpty()])>
        @if($categories->isNotEmpty())
            <div class="mobile-list">
                @foreach($categories as $category)
                    @component('layouts.partials.mobile-list-card', [
                        'title' => $category->code,
                        'meta' => $category->name,
                        'detail' => number_format($category->legal_files_count).' file'.($category->legal_files_count === 1 ? '' : 's'),
                        'badge' => $category->is_active ? 'Active' : 'Inactive',
                        'badgeTone' => $category->is_active ? 'approved' : 'rejected',
                    ])
                        @slot('actions')
                            @include('categories.partials.mobile-actions', ['category' => $category])
                        @endslot
                    @endcomponent
                @endforeach
            </div>
        @else
            @include('layouts.partials.table-empty', [
                'icon' => 'files',
                'title' => 'No case categories configured',
                'message' => 'Add categories so new files can generate numbered references by practice area.',
            ])
        @endif
    </div>
</div>

@can('categories.manage')
    <div class="modal-overlay hidden" data-modal="add-category" aria-hidden="true">
        <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="add-category-title">
            <div class="modal-header">
                <div>
                    <p class="eyebrow text-amber-700">Categories</p>
                    <h2 id="add-category-title" class="mt-1 text-2xl font-semibold">Add category</h2>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
            </div>
            <form class="mt-5 grid gap-4" method="POST" action="{{ route('categories.store') }}">
                @csrf
                <label class="block">
                    <span class="form-label">Category name</span>
                    <input class="form-input mt-1.5" name="name" placeholder="e.g. Conveyancing" required autofocus>
                </label>
                <label class="block">
                    <span class="form-label">Short code</span>
                    <input class="form-input mt-1.5" name="code" placeholder="e.g. CON" required maxlength="20">
                    <span class="form-help">Used in file numbers as CODE/YEAR/NUMBER.</span>
                </label>
                <div class="flex justify-end gap-3">
                    <button class="btn-secondary" type="button" data-modal-close>Cancel</button>
                    <button class="btn-primary">Add category</button>
                </div>
            </form>
        </div>
    </div>

    @foreach($categories as $category)
        <div class="modal-overlay hidden" data-modal="edit-category-{{ $category->id }}" aria-hidden="true">
            <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="edit-category-title-{{ $category->id }}">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow text-amber-700">{{ $category->code }}</p>
                        <h2 id="edit-category-title-{{ $category->id }}" class="mt-1 text-2xl font-semibold">Edit category</h2>
                    </div>
                    <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
                </div>
                <form
                    class="mt-5 grid gap-4"
                    method="POST"
                    action="{{ route('categories.update', $category) }}"
                    data-category-edit-form
                    data-category-id="{{ $category->id }}"
                    data-original-name="{{ $category->name }}"
                    data-original-code="{{ $category->code }}"
                    data-linked-files="{{ $category->legal_files_count }}"
                >
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="confirm_linked_updates" value="0" data-confirm-linked-updates>
                    <label class="block">
                        <span class="form-label">Category name</span>
                        <input class="form-input mt-1.5" name="name" value="{{ $category->name }}" required data-category-name>
                    </label>
                    <label class="block">
                        <span class="form-label">Short code</span>
                        <input class="form-input mt-1.5" name="code" value="{{ $category->code }}" required maxlength="20" data-category-code>
                        <span class="form-help">Saving with linked files will sync their labels and file numbers to this code.</span>
                    </label>
                    <div class="flex justify-end gap-3">
                        <button class="btn-secondary" type="button" data-modal-close>Cancel</button>
                        <button class="btn-primary" type="submit">Save category</button>
                    </div>
                </form>
            </div>
        </div>

        @if($category->legal_files_count > 0)
            <div class="modal-overlay hidden" data-modal="confirm-edit-category-{{ $category->id }}" aria-hidden="true">
                <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="confirm-edit-category-title-{{ $category->id }}">
                    <div class="modal-header">
                        <div>
                            <p class="eyebrow text-amber-700">{{ $category->code }}</p>
                            <h2 id="confirm-edit-category-title-{{ $category->id }}" class="mt-1 text-2xl font-semibold">Update linked files?</h2>
                        </div>
                        <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
                    </div>
                    <div class="mt-5 space-y-3 text-sm text-stone-600" data-confirm-edit-body>
                        <p>
                            <strong>{{ $category->name }}</strong> has
                            <strong>{{ $category->legal_files_count }}</strong> linked file{{ $category->legal_files_count === 1 ? '' : 's' }}.
                        </p>
                        <p data-confirm-edit-details></p>
                        <p>Printed labels will not update automatically.</p>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <button class="btn-secondary" type="button" data-modal-close>Cancel</button>
                        <button class="btn-primary" type="button" data-confirm-edit-submit>Update category &amp; files</button>
                    </div>
                </div>
            </div>
        @endif

        <div class="modal-overlay hidden" data-modal="delete-category-{{ $category->id }}" aria-hidden="true">
            <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="delete-category-title-{{ $category->id }}">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow text-amber-700">{{ $category->code }}</p>
                        <h2 id="delete-category-title-{{ $category->id }}" class="mt-1 text-2xl font-semibold">Delete category</h2>
                    </div>
                    <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
                </div>
                <p class="mt-5 text-sm text-stone-600">
                    Delete <strong>{{ $category->name }}</strong> ({{ $category->code }})? This cannot be undone.
                </p>
                <form class="mt-6 flex justify-end gap-3" method="POST" action="{{ route('categories.destroy', $category) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn-secondary" type="button" data-modal-close>Cancel</button>
                    <button class="btn-danger" type="submit">Delete category</button>
                </form>
            </div>
        </div>

        <div class="modal-overlay hidden" data-modal="cannot-delete-category-{{ $category->id }}" aria-hidden="true">
            <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="cannot-delete-category-title-{{ $category->id }}">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow text-amber-700">{{ $category->code }}</p>
                        <h2 id="cannot-delete-category-title-{{ $category->id }}" class="mt-1 text-2xl font-semibold">Cannot delete</h2>
                    </div>
                    <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
                </div>
                <p class="mt-5 text-sm text-stone-600">
                    <strong>{{ $category->name }}</strong> ({{ $category->code }}) has
                    <strong>{{ $category->legal_files_count }}</strong> linked file{{ $category->legal_files_count === 1 ? '' : 's' }}.
                </p>
                <p class="mt-3 text-sm text-stone-600">
                    Deactivate the category instead, or reassign those files to another category before deleting.
                </p>
                <div class="mt-6 flex justify-end">
                    <button class="btn-primary" type="button" data-modal-close">Got it</button>
                </div>
            </div>
        </div>
    @endforeach
@endcan

@can('categories.manage')
    <button class="fab-add" type="button" data-modal-open="add-category" aria-label="Add category" title="Add category">
        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
        </svg>
    </button>
@endcan
@endsection
