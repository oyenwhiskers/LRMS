@extends('layouts.app')

@section('title', 'Storage Locations')

@section('content')
<div><p class="eyebrow text-amber-700">Master data</p><h1 class="mt-1 text-3xl font-semibold">Storage locations</h1><p class="mt-2 text-sm text-stone-500">Manage the Room, Cabinet and Shelf hierarchy used to return files accurately.</p></div>

@can('storage.manage')
<section class="mt-8 grid gap-4 lg:grid-cols-3">
    <form class="border border-stone-200 bg-white p-5" method="POST" action="{{ route('storage.rooms.store') }}">@csrf
        <h2 class="font-semibold">Add room</h2><div class="mt-4 grid gap-3"><input class="form-input" name="code" placeholder="Code" required><input class="form-input" name="name" placeholder="Room name" required><button class="btn-primary">Add room</button></div>
    </form>
    <form class="border border-stone-200 bg-white p-5" method="POST" action="{{ route('storage.cabinets.store') }}">@csrf
        <h2 class="font-semibold">Add cabinet</h2><div class="mt-4 grid gap-3"><select class="form-input" name="room_id" required><option value="">Select room</option>@foreach($rooms->where('is_active', true) as $room)<option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>@endforeach</select><input class="form-input" name="code" placeholder="Code" required><input class="form-input" name="name" placeholder="Cabinet name" required><button class="btn-primary">Add cabinet</button></div>
    </form>
    <form class="border border-stone-200 bg-white p-5" method="POST" action="{{ route('storage.shelves.store') }}">@csrf
        <h2 class="font-semibold">Add shelf</h2><div class="mt-4 grid gap-3"><select class="form-input" name="cabinet_id" required><option value="">Select cabinet</option>@foreach($rooms as $room)@foreach($room->cabinets->where('is_active', true) as $cabinet)<option value="{{ $cabinet->id }}">{{ $room->code }} / {{ $cabinet->code }}</option>@endforeach @endforeach</select><input class="form-input" name="code" placeholder="Code" required><input class="form-input" name="name" placeholder="Shelf name" required><button class="btn-primary">Add shelf</button></div>
    </form>
</section>
@endcan

<section class="mt-8 space-y-5">
@forelse($rooms as $room)
    <article class="border border-stone-200 bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form class="flex flex-wrap gap-2" method="POST" action="{{ route('storage.rooms.update', $room) }}">@csrf @method('PATCH')
                <input class="form-input w-28" name="code" value="{{ $room->code }}" required><input class="form-input w-64" name="name" value="{{ $room->name }}" required>
                @can('storage.manage')<button class="btn-secondary">Save</button>@endcan
            </form>
            <form method="POST" action="{{ route('storage.rooms.toggle', $room) }}">@csrf @method('PATCH')<button class="status-badge {{ $room->is_active ? 'status-approved' : 'status-rejected' }}">{{ $room->is_active ? 'Active' : 'Inactive' }}</button></form>
        </div>
        <div class="mt-5 space-y-3 border-l-2 border-amber-500/40 pl-5">
        @foreach($room->cabinets as $cabinet)
            <div class="border border-stone-200 bg-stone-50 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <form class="flex flex-wrap gap-2" method="POST" action="{{ route('storage.cabinets.update', $cabinet) }}">@csrf @method('PATCH')<input class="form-input w-24" name="code" value="{{ $cabinet->code }}"><input class="form-input w-56" name="name" value="{{ $cabinet->name }}">@can('storage.manage')<button class="btn-secondary">Save</button>@endcan</form>
                    <form method="POST" action="{{ route('storage.cabinets.toggle', $cabinet) }}">@csrf @method('PATCH')<button class="status-badge {{ $cabinet->is_active ? 'status-approved' : 'status-rejected' }}">{{ $cabinet->is_active ? 'Active' : 'Inactive' }}</button></form>
                </div>
                <div class="mt-3 flex flex-wrap gap-3">
                @foreach($cabinet->shelves as $shelf)
                    <form class="flex items-center gap-2 border border-stone-200 bg-white p-2" method="POST" action="{{ route('storage.shelves.update', $shelf) }}">@csrf @method('PATCH')
                        <input class="form-input w-20" name="code" value="{{ $shelf->code }}"><input class="form-input w-40" name="name" value="{{ $shelf->name }}">@can('storage.manage')<button class="btn-secondary">Save</button>@endcan
                        <button formmethod="POST" formaction="{{ route('storage.shelves.toggle', $shelf) }}" class="status-badge {{ $shelf->is_active ? 'status-approved' : 'status-rejected' }}">{{ $shelf->is_active ? 'Active' : 'Inactive' }}</button>
                    </form>
                @endforeach
                </div>
            </div>
        @endforeach
        </div>
    </article>
@empty
    <p class="border border-stone-200 bg-white p-8 text-center text-stone-500">No storage locations configured.</p>
@endforelse
</section>
@endsection
