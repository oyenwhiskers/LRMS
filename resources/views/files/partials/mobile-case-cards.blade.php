@foreach($files as $file)
    @php
        $shelfLabel = $file->shelf?->full_location
            ?? match ($file->status) {
                \App\Models\LegalFile::STATUS_COMPLETED => 'Ready to store',
                \App\Models\LegalFile::STATUS_REGISTERED,
                \App\Models\LegalFile::STATUS_PENDING => 'Not stored yet',
                default => '—',
            };
        $holder = $file->currentHolder?->full_name;
        $detail = collect([$shelfLabel, $holder])->filter()->implode(' · ');
    @endphp
    @include('layouts.partials.mobile-list-card', [
        'title' => $file->reference_number,
        'meta' => $file->purchaser,
        'detail' => $detail !== '' ? $detail : null,
        'badge' => $file->statusLabel(),
        'badgeTone' => $file->statusTone(),
        'href' => route('files.show', $file),
        'ariaLabel' => 'Open file '.$file->reference_number,
    ])
@endforeach
