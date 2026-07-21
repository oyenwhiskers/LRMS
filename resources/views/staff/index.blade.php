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
                    @can('staff.qr_print')<a class="btn-secondary" href="{{ route('staff.qr', $member) }}">QR</a>@endcan
                    @can('staff.update')<a class="btn-secondary" href="{{ route('staff.edit', $member) }}">Edit</a>@endcan
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
@endsection
