@foreach($staff as $member)
    @component('layouts.partials.mobile-list-card', [
        'title' => $member->full_name,
        'meta' => $member->staff_number.' · '.($member->email ?: 'No email'),
        'detail' => ($member->position?->name ?? 'Unassigned').' · '.($member->user?->status ? ucfirst($member->user->status) : 'No account'),
        'badge' => $member->is_active ? 'Active' : 'Inactive',
        'badgeTone' => $member->is_active ? 'approved' : 'rejected',
    ])
        @slot('actions')
            @include('staff.partials.mobile-actions', ['member' => $member])
        @endslot
    @endcomponent
@endforeach
