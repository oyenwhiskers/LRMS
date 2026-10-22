@extends('layouts.app')

@section('title', 'Positions')
@section('list_layout')
@endsection

@section('content')
<div class="list-page">
    <div class="list-page__header hidden items-end justify-between gap-4 lg:flex">
        <div>
            <p class="eyebrow text-amber-700">Access control</p>
            <h1 class="mt-1 text-3xl font-semibold">Positions & permissions</h1>
            <p class="mt-2 text-sm text-stone-600">Configure one authoritative position and action-level access set for each staff member.</p>
        </div>
        @can('create', App\Models\Position::class)
            <a class="btn-icon-primary shrink-0" href="{{ route('admin.positions.create') }}" aria-label="Create position" title="Create position">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                </svg>
            </a>
        @endcan
    </div>

    <form class="list-page__filters grid gap-3 border border-stone-200 bg-white p-4 sm:grid-cols-[1fr_180px]" method="GET" data-list-filter-form>
        <input
            class="form-input"
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search position name"
            data-list-filter-search
        >
        <select class="form-input" name="status" data-list-filter-select>
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
    </form>

    {{-- Desktop table --}}
    <div @class([
        'list-page__table hidden',
        'lg:block' => $positions->isNotEmpty(),
        'list-page__table--empty lg:flex' => $positions->isEmpty(),
    ])>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Position</th>
                    <th>Status</th>
                    <th>Permissions</th>
                    <th>Staff</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            @if($positions->isNotEmpty())
                <tbody>
                    @foreach($positions as $position)
                        <tr>
                            <td><strong>{{ $position->name }}</strong></td>
                            <td><span class="status-badge {{ $position->is_active ? 'status-approved' : 'status-rejected' }}">{{ $position->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>{{ $position->permissions_count }}</td>
                            <td>{{ $position->staff_count }}</td>
                            <td>
                                <div class="flex flex-wrap items-center justify-center gap-2">
                                    @can('update', $position)
                                        <a class="btn-secondary" href="{{ route('admin.positions.edit', $position) }}">Edit</a>
                                    @endcan
                                    @can('delete', $position)
                                        @if($position->is_active)
                                            <form method="POST" action="{{ route('admin.positions.destroy', $position) }}" onsubmit="return confirm('Deactivate this position? Assigned users will lose its permissions.')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn-danger" type="submit">Deactivate</button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            @endif
        </table>
        @if($positions->isEmpty())
            @include('layouts.partials.table-empty', [
                'icon' => 'briefcase',
                'title' => 'No positions configured',
                'message' => 'Create positions to assign permissions for staff accounts.',
            ])
        @endif
    </div>

    {{-- Mobile cards --}}
    <div @class(['list-page__mobile lg:hidden', 'list-page__mobile--empty' => $positions->isEmpty()])>
        @if($positions->isNotEmpty())
            <div
                class="mobile-list"
                data-infinite-list
                data-infinite-next="{{ $positions->hasMorePages() ? $positions->currentPage() + 1 : '' }}"
                data-infinite-window="50"
            >
                @include('admin.positions.partials.mobile-position-cards', ['positions' => $positions])
            </div>
            <div class="infinite-scroll-status lg:hidden" data-infinite-status hidden></div>
            <div class="infinite-scroll-sentinel lg:hidden" data-infinite-sentinel @class(['hidden' => ! $positions->hasMorePages()]) aria-hidden="true"></div>
        @else
            @include('layouts.partials.table-empty', [
                'icon' => 'briefcase',
                'title' => 'No positions configured',
                'message' => 'Create positions to assign permissions for staff accounts.',
            ])
        @endif
    </div>

    <div class="list-page__footer hidden lg:block">{{ $positions->links() }}</div>
</div>

@can('create', App\Models\Position::class)
    <a class="fab-add" href="{{ route('admin.positions.create') }}" aria-label="Create position" title="Create position">
        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
        </svg>
    </a>
@endcan
@endsection
