@extends('layouts.app')

@section('title', 'Staff')
@section('list_layout')
@endsection

@section('content')
<div class="list-page">
    <div class="list-page__header hidden items-end justify-between gap-4 lg:flex">
        <div>
            <p class="eyebrow text-amber-700">Master data</p>
            <h1 class="mt-1 text-3xl font-semibold">Staff directory</h1>
        </div>
        @can('staff.create')
            <a class="btn-icon-primary shrink-0" href="{{ route('staff.create') }}" aria-label="Register staff" title="Register staff">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                </svg>
            </a>
        @endcan
    </div>

    <form class="list-page__filters grid gap-3 border border-stone-200 bg-white p-4 lg:grid-cols-[1fr_180px_220px]" method="GET" data-list-filter-form>
        <input
            class="form-input"
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search name, staff number, or email"
            data-list-filter-search
        >
        <select class="form-input" name="status" data-list-filter-select>
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <select class="form-input" name="position_id" data-list-filter-select>
            <option value="">All positions</option>
            @foreach($positions as $position)
                <option value="{{ $position->id }}" @selected((string) request('position_id') === (string) $position->id)>{{ $position->name }}</option>
            @endforeach
        </select>
    </form>

    {{-- Desktop table --}}
    <div @class([
        'list-page__table hidden',
        'lg:block' => $staff->isNotEmpty(),
        'list-page__table--empty lg:flex' => $staff->isEmpty(),
    ])>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Staff</th>
                    <th>Position</th>
                    <th>Account</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            @if($staff->isNotEmpty())
                <tbody>
                    @foreach($staff as $member)
                        <tr>
                            <td><strong>{{ $member->full_name }}</strong><small>{{ $member->staff_number }} · {{ $member->email ?: 'No email' }}</small></td>
                            <td>{{ $member->position?->name ?? 'Unassigned' }}</td>
                            <td>{{ $member->user?->status ? ucfirst($member->user->status) : 'No account' }}</td>
                            <td><span class="status-badge {{ $member->is_active ? 'status-approved' : 'status-rejected' }}">{{ $member->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                <div class="flex flex-wrap items-center justify-center gap-2">
                                    @can('staff.qr_print')
                                        <button class="btn-secondary" type="button" data-modal-open="staff-qr-{{ $member->id }}">QR</button>
                                    @endcan
                                    @can('staff.update')
                                        <button class="btn-secondary" type="button" data-modal-open="staff-edit-{{ $member->id }}">Edit</button>
                                    @endcan
                                    @can('staff.deactivate')
                                        <form method="POST" action="{{ route('staff.toggle', $member) }}">@csrf @method('PATCH')
                                            <button class="btn-secondary">{{ $member->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            @endif
        </table>
        @if($staff->isEmpty())
            @include('layouts.partials.table-empty', [
                'icon' => 'staff',
                'title' => 'No staff records yet',
                'message' => 'Register staff members so they can be linked to accounts and file movements.',
            ])
        @endif
    </div>

    {{-- Mobile cards --}}
    <div @class(['list-page__mobile lg:hidden', 'list-page__mobile--empty' => $staff->isEmpty()])>
        @if($staff->isNotEmpty())
            <div
                class="mobile-list"
                data-infinite-list
                data-infinite-next="{{ $staff->hasMorePages() ? $staff->currentPage() + 1 : '' }}"
                data-infinite-window="50"
            >
                @include('staff.partials.mobile-staff-cards', ['staff' => $staff])
            </div>
            <div class="infinite-scroll-status lg:hidden" data-infinite-status hidden></div>
            <div class="infinite-scroll-sentinel lg:hidden" data-infinite-sentinel @class(['hidden' => ! $staff->hasMorePages()]) aria-hidden="true"></div>
        @else
            @include('layouts.partials.table-empty', [
                'icon' => 'staff',
                'title' => 'No staff records yet',
                'message' => 'Register staff members so they can be linked to accounts and file movements.',
            ])
        @endif
    </div>

    <div class="list-page__footer hidden lg:block">{{ $staff->links() }}</div>
</div>

@foreach($staff as $member)
    @can('staff.qr_print')
        <div class="modal-overlay hidden" data-modal="staff-qr-{{ $member->id }}" aria-hidden="true">
            <div
                class="modal-panel staff-qr-modal max-w-2xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="staff-qr-title-{{ $member->id }}"
            >
                <div class="modal-header">
                    <div>
                        <p class="staff-qr-card__eyebrow">Tsang &amp; Co.</p>
                        <h2 id="staff-qr-title-{{ $member->id }}" class="staff-qr-card__title">Employee identity</h2>
                    </div>
                    <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
                </div>
                @php($staffMember = $member)
                @php($qr = $member->qr_data_uri)
                @include('staff.partials.qr-card')
            </div>
        </div>
    @endcan

    @can('staff.update')
        <div class="modal-overlay {{ old('modal_target') === 'staff-edit-'.$member->id ? '' : 'hidden' }}" data-modal="staff-edit-{{ $member->id }}" aria-hidden="{{ old('modal_target') === 'staff-edit-'.$member->id ? 'false' : 'true' }}">
            <div class="modal-panel max-w-3xl" role="dialog" aria-modal="true" aria-labelledby="staff-edit-title-{{ $member->id }}">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow text-amber-700">Staff Management</p>
                        <h2 id="staff-edit-title-{{ $member->id }}" class="mt-1 text-2xl font-semibold">Edit staff record</h2>
                    </div>
                    <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
                </div>
                <form class="mt-6 border border-stone-200 bg-white p-6 sm:p-8" method="POST" action="{{ route('staff.update', $member) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="modal_target" value="staff-edit-{{ $member->id }}">
                    @php($staffMember = $member)
                    @php($useOldInput = old('modal_target') === 'staff-edit-'.$member->id)
                    @include('staff.partials.form-fields')
                    <div class="mt-8 flex justify-end gap-3">
                        <button class="btn-primary">Save staff</button>
                        <button class="btn-secondary" type="button" data-modal-close>Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endforeach

@can('staff.create')
    <a class="fab-add" href="{{ route('staff.create') }}" aria-label="Register staff" title="Register staff">
        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
        </svg>
    </a>
@endcan
@endsection
