@extends('layouts.entry')

@section('title', 'Operator')

@php
    $advancedActive = collect([
        $filters['date_from'] ?? null,
        $filters['date_to'] ?? null,
        $filters['rack_id'] ?? null,
        $filters['shelf_id'] ?? null,
        $filters['case_type'] ?? null,
    ])->filter(fn ($value) => filled($value))->isNotEmpty();

    $selectedOperator = filled($filters['user_id'])
        ? $operators->firstWhere('id', (int) $filters['user_id'])
        : null;

    $metricFilterQuery = array_filter([
        'q' => $filters['q'] ?: null,
        'date_from' => $filters['date_from'] ?: null,
        'date_to' => $filters['date_to'] ?: null,
        'rack_id' => $filters['rack_id'] ?: null,
        'shelf_id' => $filters['shelf_id'] ?: null,
        'case_type' => $filters['case_type'] ?: null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

@section('content')
<div class="entry-ops">
    <div class="entry-ops__intro">
        <div>
            <h1 class="entry-page-title">Operator</h1>
            <p class="entry-page-lede">Semak fail yang sudah didaftar oleh operator lain.</p>
        </div>
    </div>

    <div class="entry-ops__metrics" aria-label="Ringkasan">
        <button type="button" class="entry-ops__metric" data-modal-open="ops-metric-files">
            <p class="entry-ops__metric-value">{{ $fileCount }}</p>
            <p class="entry-ops__metric-label">Fail disimpan</p>
            <p class="entry-ops__metric-hint">
                @if ($selectedOperator)
                    {{ $selectedOperator->name }}
                @else
                    Semua operator
                @endif
            </p>
        </button>
        <button type="button" class="entry-ops__metric" data-modal-open="ops-metric-operators">
            <p class="entry-ops__metric-value">{{ $operatorCount }}</p>
            <p class="entry-ops__metric-label">Operator</p>
            <p class="entry-ops__metric-hint">Sudah daftar fail</p>
        </button>
        <button type="button" class="entry-ops__metric" data-modal-open="ops-metric-pending">
            <p class="entry-ops__metric-value">{{ $pendingShelfCount }}</p>
            <p class="entry-ops__metric-label">Belum di rak</p>
            <p class="entry-ops__metric-hint">Menunggu imbas QR</p>
        </button>
        <button type="button" class="entry-ops__metric" data-modal-open="ops-metric-stored">
            <p class="entry-ops__metric-value">{{ $onShelfCount }}</p>
            <p class="entry-ops__metric-label">Di rak</p>
            <p class="entry-ops__metric-hint">Sudah ditempatkan</p>
        </button>
    </div>

    <div class="entry-ops__toolbar">
        <form class="entry-ops__filterbar" method="GET" action="{{ route('entry.operators.index') }}">
            <input type="hidden" name="date_from" value="{{ $filters['date_from'] }}">
            <input type="hidden" name="date_to" value="{{ $filters['date_to'] }}">
            <input type="hidden" name="rack_id" value="{{ $filters['rack_id'] }}">
            <input type="hidden" name="shelf_id" value="{{ $filters['shelf_id'] }}">

            <label class="entry-ops__select entry-ops__select--operator">
                <span>Operator</span>
                <select name="user_id" onchange="this.form.submit()">
                    <option value="">Semua</option>
                    @foreach ($operators as $op)
                        <option value="{{ $op->id }}" @selected((string) $filters['user_id'] === (string) $op->id)>
                            {{ $op->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="entry-ops__select entry-ops__select--type">
                <span>Jenis</span>
                <select name="case_type" onchange="this.form.submit()">
                    <option value="">Semua jenis</option>
                    <option value="{{ App\Models\LegalFile::TYPE_LOAN }}" @selected($filters['case_type'] === App\Models\LegalFile::TYPE_LOAN)>Pinjaman</option>
                    <option value="{{ App\Models\LegalFile::TYPE_SNP }}" @selected($filters['case_type'] === App\Models\LegalFile::TYPE_SNP)>Jual beli (S&amp;P)</option>
                    <option value="{{ App\Models\LegalFile::TYPE_LITIGATION }}" @selected($filters['case_type'] === App\Models\LegalFile::TYPE_LITIGATION)>Litigasi</option>
                </select>
            </label>

            <label class="entry-searchbar__field">
                <span class="sr-only">Cari no. rujukan</span>
                <span class="entry-searchbar__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="6.5"/><path stroke-linecap="round" d="M16.5 16.5 21 21"/></svg>
                </span>
                <input
                    type="search"
                    name="q"
                    value="{{ $filters['q'] }}"
                    placeholder="Cari no. rujukan atau pembeli"
                    autocomplete="off"
                >
            </label>

            <button
                class="entry-searchbar__funnel {{ $advancedActive ? 'is-active' : '' }}"
                type="button"
                data-modal-open="entry-ops-filters"
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

        @if ($advancedActive || filled($filters['q']) || filled($filters['user_id']))
            <p class="entry-searchbar__hint">
                Penapis aktif.
                <a href="{{ route('entry.operators.index') }}">Reset semua</a>
            </p>
        @endif
    </div>

    <section class="entry-ops__list" aria-labelledby="entry-ops-regs-title">
        <div class="entry-list__head entry-ops__list-head">
            <h2 id="entry-ops-regs-title">Pendaftaran</h2>
            <p class="entry-ops__list-count">{{ $scans->total() }} rekod</p>
        </div>

        <div class="entry-ops__cards">
            @forelse ($scans as $scan)
                @if ($scan->legalFile)
                    <button
                        type="button"
                        class="entry-card entry-card--action"
                        data-modal-open="operator-review-{{ $scan->id }}"
                    >
                        <div class="entry-card__body">
                            <p class="entry-card__title">{{ $scan->legalFile->reference_number }}</p>
                            <p class="entry-card__meta">
                                {{ $scan->user?->name ?: '—' }}
                                · {{ $scan->legalFile->purchaser ?: '—' }}
                            </p>
                            <p class="entry-card__shelf">
                                {{ \App\Support\LocalDate::format($scan->created_at, 'd/m/Y H:i') }}
                                @if ($scan->shelf)
                                    · {{ $scan->shelf->full_location }}
                                @elseif ($scan->status === App\Models\EntryScan::STATUS_SAVED)
                                    · Belum di rak
                                @endif
                            </p>
                        </div>
                        <span class="entry-pill">{{ $scan->statusLabel() }}</span>
                    </button>
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
        </div>

        <div class="entry-ops__table-wrap">
            @if ($scans->isEmpty())
                <div class="entry-empty">
                    <p class="entry-empty__title">Tiada hasil</p>
                    <p class="entry-empty__text">Cuba ubah carian atau tapis lanjutan.</p>
                </div>
            @else
                <table class="entry-ops__table">
                    <thead>
                        <tr>
                            <th scope="col">Rujukan</th>
                            <th scope="col">Operator</th>
                            <th scope="col">Pihak</th>
                            <th scope="col">Jenis</th>
                            <th scope="col">Lokasi</th>
                            <th scope="col">Tarikh</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($scans as $scan)
                            @continue(! $scan->legalFile)
                            <tr data-modal-open="operator-review-{{ $scan->id }}">
                                <td>
                                    <span class="entry-ops__ref">{{ $scan->legalFile->reference_number }}</span>
                                </td>
                                <td>{{ $scan->user?->name ?: '—' }}</td>
                                <td>{{ $scan->legalFile->purchaser ?: '—' }}</td>
                                <td>{{ $scan->legalFile->caseTypeLabel() }}</td>
                                <td>
                                    @if ($scan->shelf)
                                        {{ $scan->shelf->full_location }}
                                    @elseif ($scan->status === App\Models\EntryScan::STATUS_SAVED)
                                        Belum di rak
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="entry-ops__when">
                                    {{ \App\Support\LocalDate::format($scan->created_at, 'd/m/Y H:i') }}
                                </td>
                                <td>
                                    <span class="entry-ops__badge entry-ops__badge--{{ $scan->status }}">
                                        {{ $scan->statusLabel() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>

    <div>{{ $scans->links() }}</div>
</div>

@foreach ($reviewScans as $scan)
    @if ($scan->legalFile)
        @include('entry.partials.operator-review-modal', [
            'file' => $scan->legalFile,
            'scan' => $scan,
            'categories' => $categories,
        ])
    @endif
@endforeach

@include('entry.partials.operator-metric-modal', [
    'modalId' => 'ops-metric-files',
    'eyebrow' => 'Ringkasan',
    'title' => 'Fail disimpan',
    'lede' => $selectedOperator?->name ?: 'Semua operator',
    'total' => $fileCount,
    'mode' => 'files',
    'items' => $metricAllScans,
])

@include('entry.partials.operator-metric-modal', [
    'modalId' => 'ops-metric-operators',
    'eyebrow' => 'Ringkasan',
    'title' => 'Operator',
    'lede' => 'Sudah daftar fail',
    'total' => $operatorCount,
    'mode' => 'operators',
    'items' => $operatorRows,
    'filterQuery' => $metricFilterQuery,
])

@include('entry.partials.operator-metric-modal', [
    'modalId' => 'ops-metric-pending',
    'eyebrow' => 'Ringkasan',
    'title' => 'Belum di rak',
    'lede' => 'Menunggu imbas QR',
    'total' => $pendingShelfCount,
    'mode' => 'files',
    'items' => $metricPendingScans,
])

@include('entry.partials.operator-metric-modal', [
    'modalId' => 'ops-metric-stored',
    'eyebrow' => 'Ringkasan',
    'title' => 'Di rak',
    'lede' => 'Sudah ditempatkan',
    'total' => $onShelfCount,
    'mode' => 'files',
    'items' => $metricStoredScans,
])

<div class="modal-overlay hidden" data-modal="entry-ops-filters" aria-hidden="true">
    <div class="modal-panel entry-filter-modal" role="dialog" aria-modal="true" aria-labelledby="entry-ops-filters-title">
        <div class="modal-header">
            <div>
                <p class="eyebrow text-amber-700">Operator</p>
                <h2 id="entry-ops-filters-title" class="mt-1 text-xl font-semibold">Tapis lanjutan</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Tutup">Tutup</button>
        </div>

        <form class="entry-filter-modal__form" method="GET" action="{{ route('entry.operators.index') }}">
            <input type="hidden" name="q" value="{{ $filters['q'] }}">
            <input type="hidden" name="user_id" value="{{ $filters['user_id'] }}">

            <div class="entry-filter-modal__fields">
                <label class="entry-filters__field entry-ops__modal-case-type">
                    <span>Jenis kes</span>
                    <select name="case_type">
                        <option value="">Semua jenis</option>
                        <option value="{{ App\Models\LegalFile::TYPE_LOAN }}" @selected($filters['case_type'] === App\Models\LegalFile::TYPE_LOAN)>Pinjaman</option>
                        <option value="{{ App\Models\LegalFile::TYPE_SNP }}" @selected($filters['case_type'] === App\Models\LegalFile::TYPE_SNP)>Jual beli (S&amp;P)</option>
                        <option value="{{ App\Models\LegalFile::TYPE_LITIGATION }}" @selected($filters['case_type'] === App\Models\LegalFile::TYPE_LITIGATION)>Litigasi</option>
                    </select>
                </label>

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
                <a
                    class="btn-secondary w-full text-center"
                    href="{{ route('entry.operators.index', array_filter([
                        'q' => $filters['q'] ?: null,
                        'user_id' => $filters['user_id'] ?: null,
                    ])) }}"
                >Reset</a>
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
