@extends('layouts.app')

@section('title', 'Storage Locations')

@section('content')
<div>
    <p class="eyebrow text-amber-700">Master data</p>
    <h1 class="mt-1 text-3xl font-semibold">Storage locations</h1>
    <p class="mt-2 text-sm text-stone-500">Manage the room, cabinet, and shelf hierarchy used to store and return physical files accurately.</p>
</div>

@can('storage.manage')
<section class="mt-8 grid gap-4 lg:grid-cols-3">
    <form class="border border-stone-200 bg-white p-5" method="POST" action="{{ route('storage.rooms.store') }}">
        @csrf
        <h2 class="font-semibold">Add room</h2>
        <p class="mt-1 text-sm text-stone-500">Create a top-level storage area.</p>
        <div class="mt-4 grid gap-3">
            <input class="form-input" name="code" placeholder="Code" required>
            <input class="form-input" name="name" placeholder="Room name" required>
            <button class="btn-primary">Add room</button>
        </div>
    </form>

    <form class="border border-stone-200 bg-white p-5" method="POST" action="{{ route('storage.cabinets.store') }}">
        @csrf
        <h2 class="font-semibold">Add cabinet</h2>
        <p class="mt-1 text-sm text-stone-500">Place a cabinet inside an active room.</p>
        <div class="mt-4 grid gap-3">
            <select class="form-input" name="room_id" required>
                <option value="">Select room</option>
                @foreach($rooms->where('is_active', true) as $room)
                    <option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>
                @endforeach
            </select>
            <input class="form-input" name="code" placeholder="Code" required>
            <input class="form-input" name="name" placeholder="Cabinet name" required>
            <button class="btn-primary">Add cabinet</button>
        </div>
    </form>

    <form class="border border-stone-200 bg-white p-5" method="POST" action="{{ route('storage.shelves.store') }}">
        @csrf
        <h2 class="font-semibold">Add shelf</h2>
        <p class="mt-1 text-sm text-stone-500">Add a shelf inside an active cabinet.</p>
        <div class="mt-4 grid gap-3">
            <select class="form-input" name="cabinet_id" required>
                <option value="">Select cabinet</option>
                @foreach($rooms as $room)
                    @foreach($room->cabinets->where('is_active', true) as $cabinet)
                        <option value="{{ $cabinet->id }}">{{ $room->code }} / {{ $cabinet->code }}</option>
                    @endforeach
                @endforeach
            </select>
            <input class="form-input" name="code" placeholder="Code" required>
            <input class="form-input" name="name" placeholder="Shelf name" required>
            <button class="btn-primary">Add shelf</button>
        </div>
    </form>
</section>
@endcan

<section class="mt-8 space-y-5">
@forelse($rooms as $room)
    @php
        $cabinetCount = $room->cabinets->count();
        $activeCabinetCount = $room->cabinets->where('is_active', true)->count();
        $shelfCount = $room->cabinets->sum(fn ($cabinet) => $cabinet->shelves->count());
    @endphp
    <article class="border border-stone-200 bg-white p-5 sm:p-6">
        <div class="storage-room-header">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-stone-500">Room {{ $room->code }}</p>
                <h2 class="mt-1 text-xl font-semibold text-stone-900">{{ $room->name }}</h2>
                <p class="mt-2 text-sm text-stone-500">{{ $cabinetCount }} cabinets, {{ $shelfCount }} shelves, {{ $activeCabinetCount }} active cabinets.</p>
            </div>
            <form method="POST" action="{{ route('storage.rooms.toggle', $room) }}">
                @csrf
                @method('PATCH')
                <button class="status-badge {{ $room->is_active ? 'status-approved' : 'status-rejected' }}">{{ $room->is_active ? 'Active' : 'Inactive' }}</button>
            </form>
        </div>

        <form class="storage-entity-form mt-5" method="POST" action="{{ route('storage.rooms.update', $room) }}" data-inline-edit>
            @csrf
            @method('PATCH')
            <div class="storage-entity-fields">
                <label class="storage-inline-field">
                    <span class="form-label">Code</span>
                    <span class="storage-field-display" data-inline-display>{{ $room->code }}</span>
                    <input class="form-input hidden" name="code" value="{{ $room->code }}" required data-inline-input>
                </label>
                <label class="storage-inline-field">
                    <span class="form-label">Room name</span>
                    <span class="storage-field-display" data-inline-display>{{ $room->name }}</span>
                    <input class="form-input hidden" name="name" value="{{ $room->name }}" required data-inline-input>
                </label>
            </div>
            <div class="storage-inline-actions">
                @can('storage.manage')
                    <button type="button" class="btn-secondary" data-inline-edit-trigger>Edit</button>
                    <div class="hidden items-center gap-2" data-inline-edit-actions>
                        <button class="btn-primary">Save room</button>
                        <button type="button" class="btn-secondary" data-inline-cancel>Cancel</button>
                    </div>
                @endcan
            </div>
        </form>

        <div class="mt-6 space-y-4">
        @forelse($room->cabinets as $cabinet)
            @php
                $activeShelfCount = $cabinet->shelves->where('is_active', true)->count();
            @endphp
            <section class="border border-stone-200 bg-stone-50 p-4 sm:p-5">
                <div class="storage-cabinet-header">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.12em] text-stone-500">Cabinet {{ $room->code }} / {{ $cabinet->code }}</p>
                        <h3 class="mt-1 text-lg font-semibold text-stone-900">{{ $cabinet->name }}</h3>
                        <p class="mt-1 text-sm text-stone-500">{{ $cabinet->shelves->count() }} shelves, {{ $activeShelfCount }} active.</p>
                    </div>
                    <form method="POST" action="{{ route('storage.cabinets.toggle', $cabinet) }}">
                        @csrf
                        @method('PATCH')
                        <button class="status-badge {{ $cabinet->is_active ? 'status-approved' : 'status-rejected' }}">{{ $cabinet->is_active ? 'Active' : 'Inactive' }}</button>
                    </form>
                </div>

                <form class="storage-entity-form mt-4" method="POST" action="{{ route('storage.cabinets.update', $cabinet) }}" data-inline-edit>
                    @csrf
                    @method('PATCH')
                    <div class="storage-entity-fields">
                        <label class="storage-inline-field">
                            <span class="form-label">Code</span>
                            <span class="storage-field-display" data-inline-display>{{ $cabinet->code }}</span>
                            <input class="form-input hidden" name="code" value="{{ $cabinet->code }}" required data-inline-input>
                        </label>
                        <label class="storage-inline-field">
                            <span class="form-label">Cabinet name</span>
                            <span class="storage-field-display" data-inline-display>{{ $cabinet->name }}</span>
                            <input class="form-input hidden" name="name" value="{{ $cabinet->name }}" required data-inline-input>
                        </label>
                    </div>
                    <div class="storage-inline-actions">
                        @can('storage.manage')
                            <button type="button" class="btn-secondary" data-inline-edit-trigger>Edit</button>
                            <div class="hidden items-center gap-2" data-inline-edit-actions>
                                <button class="btn-primary">Save cabinet</button>
                                <button type="button" class="btn-secondary" data-inline-cancel>Cancel</button>
                            </div>
                        @endcan
                    </div>
                </form>

                <div class="mt-4 overflow-hidden border border-stone-200 bg-white">
                    <div class="hidden grid-cols-[120px_minmax(0,1fr)_auto] border-b border-stone-200 bg-stone-100 px-4 py-3 text-xs font-bold uppercase tracking-[0.12em] text-stone-600 md:grid">
                        <span>Shelf code</span>
                        <span>Shelf name</span>
                        <span class="text-right">Actions</span>
                    </div>
                    <div class="divide-y divide-stone-200">
                    @forelse($cabinet->shelves as $shelf)
                        <form class="storage-shelf-row" method="POST" action="{{ route('storage.shelves.update', $shelf) }}" data-inline-edit>
                            @csrf
                            @method('PATCH')
                            <label class="storage-inline-field">
                                <span class="form-label md:hidden">Shelf code</span>
                                <span class="storage-field-display" data-inline-display>{{ $shelf->code }}</span>
                                <input class="form-input hidden" name="code" value="{{ $shelf->code }}" data-inline-input>
                            </label>
                            <label class="storage-inline-field">
                                <span class="form-label md:hidden">Shelf name</span>
                                <span class="storage-field-display" data-inline-display>{{ $shelf->name }}</span>
                                <input class="form-input hidden" name="name" value="{{ $shelf->name }}" data-inline-input>
                            </label>
                            <div class="storage-shelf-actions">
                                @can('storage.manage')
                                    <button type="button" class="btn-secondary" data-inline-edit-trigger>Edit</button>
                                    <div class="hidden items-center gap-2" data-inline-edit-actions>
                                        <button class="btn-primary">Save shelf</button>
                                        <button type="button" class="btn-secondary" data-inline-cancel>Cancel</button>
                                    </div>
                                @endcan
                                <button formmethod="POST" formaction="{{ route('storage.shelves.toggle', $shelf) }}" class="status-badge {{ $shelf->is_active ? 'status-approved' : 'status-rejected' }}">{{ $shelf->is_active ? 'Active' : 'Inactive' }}</button>
                            </div>
                        </form>
                    @empty
                        <div class="p-5 text-sm text-stone-500">No shelves have been added to this cabinet yet.</div>
                    @endforelse
                    </div>
                </div>
            </section>
        @empty
            <div class="border border-dashed border-stone-300 bg-stone-50 p-6 text-sm text-stone-500">No cabinets have been added to this room yet.</div>
        @endforelse
        </div>
    </article>
@empty
    <p class="border border-stone-200 bg-white p-8 text-center text-stone-500">No storage locations configured.</p>
@endforelse
</section>
@endsection
