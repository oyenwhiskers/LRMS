@foreach($customers as $customer)
    @php
        $missing = (int) $customer->missing_cases;
        $detailParts = [
            $customer->total_cases.' cases',
            $customer->stored_cases.' stored',
            $customer->borrowed_cases.' borrowed',
        ];
    @endphp
    @include('layouts.partials.mobile-list-card', [
        'title' => $customer->purchaser,
        'meta' => $customer->last_activity_at
            ? 'Updated '.\Illuminate\Support\Carbon::parse($customer->last_activity_at)->format('d M Y')
            : null,
        'detail' => implode(' · ', $detailParts),
        'badge' => $missing > 0 ? $missing.' missing' : null,
        'badgeTone' => 'rejected',
        'href' => route('files.index', ['view' => 'case', 'search' => $customer->purchaser]),
        'ariaLabel' => 'View cases for '.$customer->purchaser,
    ])
@endforeach
