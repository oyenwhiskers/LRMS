@foreach($movements as $movement)
    @php
        $detailParts = array_values(array_filter([
            $movement->historyPersonLabel(),
            $movement->historyShelfLabel(),
            $movement->processor?->name ? 'By '.$movement->processor->name : null,
        ]));
        $fileHref = $movement->legalFile ? route('files.show', $movement->legalFile) : null;
    @endphp
    @include('layouts.partials.mobile-list-card', [
        'title' => $movement->legalFile?->reference_number ?? 'Unknown file',
        'meta' => $movement->occurred_at?->format('d M Y H:i'),
        'detail' => $detailParts ? implode(' · ', $detailParts) : null,
        'badge' => $movement->typeLabel(),
        'badgeTone' => $movement->historyBadgeTone(),
        'href' => $fileHref,
        'ariaLabel' => $fileHref
            ? 'Open file '.$movement->legalFile->reference_number
            : $movement->typeLabel(),
    ])
@endforeach
