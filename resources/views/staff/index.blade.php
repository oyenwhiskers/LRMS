@extends('layouts.app')

@section('title', 'Staff')

@section('content')
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div><p class="eyebrow text-amber-700">Master data</p><h1 class="mt-1 text-3xl font-semibold">Staff directory</h1></div>
    @can('staff.create')<a class="btn-primary" href="{{ route('staff.create') }}">Register staff</a>@endcan
</div>

<div class="mt-8 overflow-x-auto border border-stone-200 bg-white">
    <table class="data-table">
        <thead><tr><th>Staff</th><th>Position</th><th>Account</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
        @forelse($staff as $member)
            <tr>
                <td><strong>{{ $member->full_name }}</strong><small>{{ $member->staff_number }} · {{ $member->email ?: 'No email' }}</small></td>
                <td>{{ $member->position?->name ?? 'Unassigned' }}</td>
                <td>{{ $member->user?->status ? ucfirst($member->user->status) : 'No account' }}</td>
                <td><span class="status-badge {{ $member->is_active ? 'status-approved' : 'status-rejected' }}">{{ $member->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td><div class="flex justify-end gap-2">
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
                </div></td>
            </tr>
        @empty
            <tr><td colspan="5" class="py-10 text-center text-stone-500">No staff records yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $staff->links() }}</div>

@foreach($staff as $member)
    @can('staff.qr_print')
        <div class="modal-overlay hidden" data-modal="staff-qr-{{ $member->id }}" aria-hidden="true">
            <div class="modal-panel max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="staff-qr-title-{{ $member->id }}">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow text-amber-700">Staff QR</p>
                        <h2 id="staff-qr-title-{{ $member->id }}" class="mt-1 text-2xl font-semibold">Employee identity</h2>
                    </div>
                    <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
                </div>
                @php($staffMember = $member)
                @php($qr = $member->qr_data_uri)
                @include('staff.partials.qr-card')
                <div class="mt-5 flex justify-end gap-3">
                    <a class="btn-secondary" href="{{ route('staff.qr', $member) }}" target="_blank" rel="noopener">Print view</a>
                </div>
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
@endsection
