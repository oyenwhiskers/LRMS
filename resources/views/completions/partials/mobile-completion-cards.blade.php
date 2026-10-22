@foreach($files as $file)
    @component('layouts.partials.mobile-list-card', [
        'title' => $file->reference_number,
        'meta' => $file->purchaser,
        'detail' => trim(($file->personInCharge?->full_name ?? '—').' · '.($file->updated_at?->format('d M Y H:i') ?? '—')),
        'href' => route('files.show', $file),
        'ariaLabel' => 'Open file '.$file->reference_number,
    ])
        @slot('actions')
            @include('completions.partials.mobile-actions', ['file' => $file])
        @endslot
    @endcomponent
@endforeach
