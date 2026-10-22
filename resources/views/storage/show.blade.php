@extends('layouts.app')

@section('title', $rack->code.' · Storage')
@section('back', route('storage.index'))

@section('content')
<header class="flex flex-col gap-4">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="eyebrow text-amber-700">{{ config('lrms.storage.room_name') }}</p>
            <h1 class="mt-1 text-3xl font-semibold">{{ $rack->code }} — {{ $rack->name }}</h1>
            <p class="mt-2 text-sm text-stone-500">{{ $rack->shelves->count() }} shelves configured.</p>
        </div>
        <a class="btn-secondary hidden shrink-0 lg:inline-flex" href="{{ route('storage.index') }}">Back</a>
    </div>
    @can('storage.manage')
        <div class="flex w-full items-stretch gap-2">
            <button class="btn-primary min-w-0 flex-1 justify-center" type="button" data-modal-open="add-shelf">+ Add shelf</button>
        </div>
    @endcan
    @if(auth()->user()->can('storage.view') && auth()->user()->can('labels.print'))
        <div class="flex flex-wrap gap-2">
            <button class="btn-secondary" type="button" data-storage-label-kind="rack" data-storage-label-print-url="{{ route('storage.racks.label', $rack) }}">Print rack sign</button>
            <a class="btn-secondary" href="{{ route('storage.racks.label.pdf', $rack) }}">Rack sign PDF</a>
            @if($rack->shelves->isNotEmpty())
                <button class="btn-secondary" type="button" data-storage-label-print-url="{{ route('storage.racks.shelves-labels', $rack) }}">Print all shelf labels</button>
                <a class="btn-secondary" href="{{ route('storage.racks.shelves-labels.pdf', $rack) }}">Shelf labels PDF</a>
            @endif
        </div>
    @endif
</header>

@can('storage.manage')
    <section class="mt-8 border border-stone-200 bg-white p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <h2 class="text-lg font-semibold">Rack details</h2>
            <div class="flex gap-2">
                <button class="btn-secondary" type="button" data-modal-open="edit-rack">Edit</button>
                <button class="btn-danger" type="button" data-modal-open="delete-rack">Delete</button>
            </div>
        </div>
        <dl class="mt-5 grid gap-5 sm:grid-cols-3">
            <div>
                <dt class="form-label">Rack code</dt>
                <dd class="mt-1.5 text-sm font-medium text-stone-900">{{ $rack->code }}</dd>
            </div>
            <div>
                <dt class="form-label">Rack name</dt>
                <dd class="mt-1.5 text-sm font-medium text-stone-900">{{ $rack->name }}</dd>
            </div>
            <div>
                <dt class="form-label">Status</dt>
                <dd class="mt-1.5">
                    <span class="status-badge {{ $rack->is_active ? 'status-approved' : 'status-rejected' }}">
                        {{ $rack->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </dd>
            </div>
        </dl>
    </section>
@endcan

<section class="mt-8 overflow-hidden border border-stone-200 bg-white">
    <div class="border-b border-stone-200 px-6 py-4">
        <h2 class="text-lg font-semibold">Shelves</h2>
    </div>
    <div @class(['overflow-x-auto', 'min-h-64' => $rack->shelves->isEmpty()])>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Shelf code</th>
                    <th>Shelf name</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            @if($rack->shelves->isNotEmpty())
                <tbody>
                    @foreach($rack->shelves as $shelf)
                        <tr>
                            <td class="font-semibold">{{ $shelf->code }}</td>
                            <td>{{ $shelf->name }}</td>
                            <td class="text-center">
                                <span class="status-badge {{ $shelf->is_active ? 'status-approved' : 'status-rejected' }}">
                                    {{ $shelf->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="flex justify-center gap-2">
                                    @if(auth()->user()->can('storage.view') && auth()->user()->can('labels.print'))
                                        <button class="btn-secondary" type="button" data-storage-label-print-url="{{ route('storage.shelves.label', $shelf) }}">Print label</button>
                                        <a class="btn-secondary" href="{{ route('storage.shelves.label.pdf', $shelf) }}">PDF</a>
                                    @endif
                                    @can('storage.manage')
                                        <button
                                            class="btn-secondary"
                                            type="button"
                                            data-modal-open="edit-shelf-{{ $shelf->id }}"
                                        >Edit</button>
                                        <button class="btn-danger" type="button" data-modal-open="delete-shelf-{{ $shelf->id }}">Delete</button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            @endif
        </table>
        @if($rack->shelves->isEmpty())
            @include('layouts.partials.table-empty', [
                'icon' => 'archive',
                'title' => 'No shelves on this rack yet',
                'message' => 'Add a shelf to start placing files in this rack.',
            ])
        @endif
    </div>
</section>

@can('storage.manage')
    <div class="modal-overlay hidden" data-modal="delete-rack" aria-hidden="true">
        <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="delete-rack-title">
            <div class="modal-header">
                <div>
                    <p class="eyebrow text-amber-700">{{ $rack->code }}</p>
                    <h2 id="delete-rack-title" class="mt-1 text-2xl font-semibold">Delete rack</h2>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
            </div>
            <p class="mt-5 text-sm text-stone-600">Delete <strong>{{ $rack->name }}</strong> and its {{ $rack->shelves->count() }} shelves? This cannot be undone. Shelves linked to files or movement history prevent deletion.</p>
            <form class="mt-6 flex justify-end gap-3" method="POST" action="{{ route('storage.racks.destroy', $rack) }}">
                @csrf
                @method('DELETE')
                <button class="btn-secondary" type="button" data-modal-close>Cancel</button>
                <button class="btn-danger" type="submit">Delete rack</button>
            </form>
        </div>
    </div>

    <div
        class="modal-overlay {{ old('modal_target') === 'edit-rack' ? '' : 'hidden' }}"
        data-modal="edit-rack"
        aria-hidden="{{ old('modal_target') === 'edit-rack' ? 'false' : 'true' }}"
    >
        <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="edit-rack-title">
            <div class="modal-header">
                <div>
                    <p class="eyebrow text-amber-700">{{ config('lrms.storage.room_name') }}</p>
                    <h2 id="edit-rack-title" class="mt-1 text-2xl font-semibold">Edit rack</h2>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
            </div>
            <form class="mt-5 grid gap-4" method="POST" action="{{ route('storage.racks.update', $rack) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="modal_target" value="edit-rack">
                <label class="block">
                    <span class="form-label">Rack code</span>
                    <input
                        class="form-input mt-1.5"
                        name="code"
                        value="{{ old('modal_target') === 'edit-rack' ? old('code') : $rack->code }}"
                        required
                    >
                    @error('code')
                        @if(old('modal_target') === 'edit-rack')
                            <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                        @endif
                    @enderror
                </label>
                <label class="block">
                    <span class="form-label">Rack name</span>
                    <input
                        class="form-input mt-1.5"
                        name="name"
                        value="{{ old('modal_target') === 'edit-rack' ? old('name') : $rack->name }}"
                        required
                    >
                    @error('name')
                        @if(old('modal_target') === 'edit-rack')
                            <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                        @endif
                    @enderror
                </label>
                <label class="block">
                    <span class="form-label">Status</span>
                    <select class="form-input mt-1.5" name="is_active" required>
                        @php($rackActiveValue = old('modal_target') === 'edit-rack' ? old('is_active') : ($rack->is_active ? '1' : '0'))
                        <option value="1" @selected((string) $rackActiveValue === '1')>Active</option>
                        <option value="0" @selected((string) $rackActiveValue === '0')>Inactive</option>
                    </select>
                    @error('is_active')
                        @if(old('modal_target') === 'edit-rack')
                            <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                        @endif
                    @enderror
                </label>
                <div class="flex justify-end gap-3">
                    <button class="btn-secondary" type="button" data-modal-close>Cancel</button>
                    <button class="btn-primary">Save rack</button>
                </div>
            </form>
        </div>
    </div>

    <div
        class="modal-overlay {{ old('modal_target') === 'add-shelf' ? '' : 'hidden' }}"
        data-modal="add-shelf"
        aria-hidden="{{ old('modal_target') === 'add-shelf' ? 'false' : 'true' }}"
    >
        <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="add-shelf-title">
            <div class="modal-header">
                <div>
                    <p class="eyebrow text-amber-700">{{ $rack->code }}</p>
                    <h2 id="add-shelf-title" class="mt-1 text-2xl font-semibold">Add shelf</h2>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
            </div>
            <form class="mt-5 grid gap-4" method="POST" action="{{ route('storage.shelves.store', $rack) }}">
                @csrf
                <input type="hidden" name="modal_target" value="add-shelf">
                <label class="block">
                    <span class="form-label">Shelf code</span>
                    <input
                        class="form-input mt-1.5"
                        name="code"
                        value="{{ old('modal_target') === 'add-shelf' ? old('code') : '' }}"
                        placeholder="e.g. S1"
                        required
                        autofocus
                    >
                    @error('code')
                        @if(old('modal_target') === 'add-shelf')
                            <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                        @endif
                    @enderror
                </label>
                <label class="block">
                    <span class="form-label">Shelf name</span>
                    <input
                        class="form-input mt-1.5"
                        name="name"
                        value="{{ old('modal_target') === 'add-shelf' ? old('name') : '' }}"
                        placeholder="e.g. Shelf Alpha"
                        required
                    >
                    @error('name')
                        @if(old('modal_target') === 'add-shelf')
                            <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                        @endif
                    @enderror
                </label>
                <div class="flex justify-end gap-3">
                    <button class="btn-secondary" type="button" data-modal-close>Cancel</button>
                    <button class="btn-primary">Add shelf</button>
                </div>
            </form>
        </div>
    </div>

    @foreach($rack->shelves as $shelf)
        <div class="modal-overlay hidden" data-modal="delete-shelf-{{ $shelf->id }}" aria-hidden="true">
            <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="delete-shelf-title-{{ $shelf->id }}">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow text-amber-700">{{ $rack->code }} / {{ $shelf->code }}</p>
                        <h2 id="delete-shelf-title-{{ $shelf->id }}" class="mt-1 text-2xl font-semibold">Delete shelf</h2>
                    </div>
                    <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
                </div>
                <p class="mt-5 text-sm text-stone-600">Delete <strong>{{ $shelf->name }}</strong>? This cannot be undone. Shelves linked to files or movement history cannot be deleted.</p>
                <form class="mt-6 flex justify-end gap-3" method="POST" action="{{ route('storage.shelves.destroy', $shelf) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn-secondary" type="button" data-modal-close>Cancel</button>
                    <button class="btn-danger" type="submit">Delete shelf</button>
                </form>
            </div>
        </div>

        @php($editModal = 'edit-shelf-'.$shelf->id)
        <div
            class="modal-overlay {{ old('modal_target') === $editModal ? '' : 'hidden' }}"
            data-modal="{{ $editModal }}"
            aria-hidden="{{ old('modal_target') === $editModal ? 'false' : 'true' }}"
        >
            <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="edit-shelf-title-{{ $shelf->id }}">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow text-amber-700">{{ $rack->code }} · {{ $shelf->code }}</p>
                        <h2 id="edit-shelf-title-{{ $shelf->id }}" class="mt-1 text-2xl font-semibold">Edit shelf</h2>
                    </div>
                    <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
                </div>
                <form class="mt-5 grid gap-4" method="POST" action="{{ route('storage.shelves.update', $shelf) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="modal_target" value="{{ $editModal }}">
                    <label class="block">
                        <span class="form-label">Shelf code</span>
                        <input
                            class="form-input mt-1.5"
                            name="code"
                            value="{{ old('modal_target') === $editModal ? old('code') : $shelf->code }}"
                            required
                        >
                        @error('code')
                            @if(old('modal_target') === $editModal)
                                <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                            @endif
                        @enderror
                    </label>
                    <label class="block">
                        <span class="form-label">Shelf name</span>
                        <input
                            class="form-input mt-1.5"
                            name="name"
                            value="{{ old('modal_target') === $editModal ? old('name') : $shelf->name }}"
                            required
                        >
                        @error('name')
                            @if(old('modal_target') === $editModal)
                                <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                            @endif
                        @enderror
                    </label>
                    <label class="block">
                        <span class="form-label">Status</span>
                        <select class="form-input mt-1.5" name="is_active" required>
                            @php($activeValue = old('modal_target') === $editModal ? old('is_active') : ($shelf->is_active ? '1' : '0'))
                            <option value="1" @selected((string) $activeValue === '1')>Active</option>
                            <option value="0" @selected((string) $activeValue === '0')>Inactive</option>
                        </select>
                        @error('is_active')
                            @if(old('modal_target') === $editModal)
                                <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                            @endif
                        @enderror
                    </label>
                    <div class="flex justify-end gap-3">
                        <button class="btn-secondary" type="button" data-modal-close>Cancel</button>
                        <button class="btn-primary">Save shelf</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endcan
@endsection
