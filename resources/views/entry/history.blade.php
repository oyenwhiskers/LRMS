@extends('layouts.entry')

@section('title', 'Sejarah')

@php
    $advancedActive = collect([
        $filters['date_from'] ?? null,
        $filters['date_to'] ?? null,
        $filters['rack_id'] ?? null,
        $filters['shelf_id'] ?? null,
    ])->filter(fn ($value) => filled($value))->isNotEmpty();
@endphp

@section('content')
<div class="entry-home">
    <div>
        <h1 class="entry-page-title">Sejarah imbasan</h1>
        <p class="entry-page-lede">Cari fail mengikut no. rujukan atau tapis lanjutan.</p>
    </div>

    <form class="entry-searchbar" method="GET" action="{{ route('entry.history') }}">
        <input type="hidden" name="date_from" value="{{ $filters['date_from'] }}">
        <input type="hidden" name="date_to" value="{{ $filters['date_to'] }}">
        <input type="hidden" name="rack_id" value="{{ $filters['rack_id'] }}">
        <input type="hidden" name="shelf_id" value="{{ $filters['shelf_id'] }}">

        <label class="entry-searchbar__field">
            <span class="sr-only">Cari no. rujukan</span>
            <span class="entry-searchbar__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="6.5"/><path stroke-linecap="round" d="M16.5 16.5 21 21"/></svg>
            </span>
            <input
                type="search"
                name="q"
                value="{{ $filters['q'] }}"
                placeholder="Cari no. rujukan"
                autocomplete="off"
            >
        </label>

        <button
            class="entry-searchbar__funnel {{ $advancedActive ? 'is-active' : '' }}"
            type="button"
            data-modal-open="entry-history-filters"
            aria-label="Tapis lanjutan"
            title="Tapis lanjutan"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M7 12h10M10 18h4"/>
            </svg>
            @if ($advancedActive)
                <span class="entry-searchbar__dot" aria-hidden="true"></span>
            @endif
        </button>
    </form>

    @if ($advancedActive || filled($filters['q']))
        <p class="entry-searchbar__hint">
            Penapis aktif.
            <a href="{{ route('entry.history') }}">Reset semua</a>
        </p>
    @endif

    <section class="entry-list">
        @forelse ($scans as $scan)
            @if ($scan->legalFile)
                @php
                    $needsFix = $scan->review_status === App\Models\EntryScan::REVIEW_NEEDS_CORRECTION;
                    $pillClass = match ($scan->review_status) {
                        App\Models\EntryScan::REVIEW_NEEDS_CORRECTION => 'entry-pill entry-pill--needs-correction',
                        App\Models\EntryScan::REVIEW_VERIFIED => 'entry-pill entry-pill--verified',
                        default => 'entry-pill',
                    };
                    $pillLabel = in_array($scan->review_status, [
                        App\Models\EntryScan::REVIEW_NEEDS_CORRECTION,
                        App\Models\EntryScan::REVIEW_VERIFIED,
                    ], true)
                        ? $scan->reviewStatusLabel()
                        : $scan->statusLabel();
                @endphp
                @if ($scan->status === App\Models\EntryScan::STATUS_SAVED && ! $needsFix)
                    <a
                        class="entry-card entry-card--action"
                        href="{{ route('entry.shelf', $scan) }}"
                    >
                        <div class="entry-card__body">
                            <p class="entry-card__title">{{ $scan->legalFile->reference_number }}</p>
                            <p class="entry-card__meta">{{ $scan->legalFile->purchaser ?: '—' }}</p>
                            <p class="entry-card__shelf">
                                {{ \App\Support\LocalDate::format($scan->created_at, 'Y-m-d H:i') }}
                                · Belum di rak · imbas QR rak
                            </p>
                        </div>
                        <span class="{{ $pillClass }}">{{ $pillLabel }}</span>
                    </a>
                @else
                    <button
                        type="button"
                        class="entry-card entry-card--action"
                        data-modal-open="file-label-{{ $scan->legalFile->id }}"
                    >
                        <div class="entry-card__body">
                            <p class="entry-card__title">{{ $scan->legalFile->reference_number }}</p>
                            <p class="entry-card__meta">{{ $scan->legalFile->purchaser ?: '—' }}</p>
                            <p class="entry-card__shelf">
                                {{ \App\Support\LocalDate::format($scan->created_at, 'Y-m-d H:i') }}
                                @if ($scan->shelf)
                                    · {{ $scan->shelf->full_location }}
                                @elseif ($scan->status === App\Models\EntryScan::STATUS_SAVED)
                                    · Belum di rak
                                @endif
                            </p>
                        </div>
                        <span class="{{ $pillClass }}">{{ $pillLabel }}</span>
                    </button>
                @endif
            @else
                <article class="entry-card">
                    <div class="entry-card__body">
                        <p class="entry-card__title">—</p>
                        <p class="entry-card__meta">—</p>
                    </div>
                    <span class="entry-pill">{{ $scan->statusLabel() }}</span>
                </article>
            @endif
        @empty
            <div class="entry-empty">
                <div class="entry-empty__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5v5l3 1.75M20 12a8 8 0 11-8-8 8.3 8.3 0 013.5.75L18 3v4.5h-4.5"/></svg>
                </div>
                <p class="entry-empty__title">Tiada hasil</p>
                <p class="entry-empty__text">Cuba ubah carian atau tapis lanjutan.</p>
            </div>
        @endforelse
    </section>

    <div>{{ $scans->links() }}</div>
</div>

@foreach ($scans as $scan)
    @if (
        $scan->legalFile
        && (
            $scan->status === App\Models\EntryScan::STATUS_STORED
            || $scan->review_status === App\Models\EntryScan::REVIEW_NEEDS_CORRECTION
        )
        && isset($labelQrs[$scan->legalFile->id])
    )
        @include('entry.partials.file-label-modal', [
            'file' => $scan->legalFile,
            'qr' => $labelQrs[$scan->legalFile->id],
            'scan' => $scan,
            'categories' => $categories,
        ])
    @endif
@endforeach

<div class="modal-overlay hidden" data-modal="entry-history-filters" aria-hidden="true">
    <div class="modal-panel entry-filter-modal" role="dialog" aria-modal="true" aria-labelledby="entry-history-filters-title">
        <div class="modal-header">
            <div>
                <p class="eyebrow text-amber-700">Sejarah</p>
                <h2 id="entry-history-filters-title" class="mt-1 text-xl font-semibold">Tapis lanjutan</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Tutup">Tutup</button>
        </div>

        <form class="entry-filter-modal__form" method="GET" action="{{ route('entry.history') }}">
            <input type="hidden" name="q" value="{{ $filters['q'] }}">

            <div class="entry-filter-modal__fields">
                <label class="entry-filters__field">
                    <span>Dari tarikh</span>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}">
                </label>

                <label class="entry-filters__field">
                    <span>Hingga tarikh</span>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}">
                </label>

                <label class="entry-filters__field">
                    <span>Rak</span>
                    <select name="rack_id" data-entry-rack-filter>
                        <option value="">Semua rak</option>
                        @foreach ($racks as $rack)
                            <option value="{{ $rack->id }}" @selected((string) $filters['rack_id'] === (string) $rack->id)>
                                {{ $rack->code }}{{ $rack->name ? ' — '.$rack->name : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="entry-filters__field">
                    <span>Shelf</span>
                    <select name="shelf_id" data-entry-shelf-filter>
                        <option value="">Semua shelf</option>
                        @foreach ($shelves as $shelf)
                            <option
                                value="{{ $shelf->id }}"
                                data-rack-id="{{ $shelf->rack_id }}"
                                @selected((string) $filters['shelf_id'] === (string) $shelf->id)
                            >
                                {{ $shelf->rack?->code }}/{{ $shelf->code }}{{ $shelf->name ? ' — '.$shelf->name : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="entry-filter-modal__actions">
                <a class="btn-secondary w-full text-center" href="{{ route('entry.history', ['q' => $filters['q'] ?: null]) }}">Reset</a>
                <button class="btn-primary w-full" type="submit">Guna tapis</button>
            </div>
        </form>
    </div>
</div>

<script>
(() => {
    const rack = document.querySelector('[data-entry-rack-filter]');
    const shelf = document.querySelector('[data-entry-shelf-filter]');
    if (!rack || !shelf) return;

    const options = Array.from(shelf.querySelectorAll('option[data-rack-id]'));

    const syncShelves = () => {
        const rackId = rack.value;
        options.forEach((option) => {
            const match = !rackId || option.dataset.rackId === rackId;
            option.hidden = !match;
            if (!match && option.selected) {
                shelf.value = '';
            }
        });
    };

    rack.addEventListener('change', syncShelves);
    syncShelves();
})();
</script>
@endsection
