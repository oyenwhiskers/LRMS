@foreach($positions as $position)
    @component('layouts.partials.mobile-list-card', [
        'title' => $position->name,
        'detail' => number_format($position->permissions_count).' permissions · '.number_format($position->staff_count).' staff',
        'badge' => $position->is_active ? 'Active' : 'Inactive',
        'badgeTone' => $position->is_active ? 'approved' : 'rejected',
    ])
        @slot('actions')
            @include('admin.positions.partials.mobile-actions', ['position' => $position])
        @endslot
    @endcomponent
@endforeach
