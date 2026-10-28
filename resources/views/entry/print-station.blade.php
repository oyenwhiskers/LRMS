@extends('layouts.entry')

@section('title', 'Stesen cetak')

@section('content')
@php
    $statusFilter = $filters['status'] ?? 'pending';
    $reviewFilter = $filters['review'] ?? 'all';
    $countLabel = match ($statusFilter) {
        'printed' => 'dicetak',
        'all' => 'job',
        default => 'belum dicetak',
    };
    $printSelectedLabel = $statusFilter === 'printed' ? 'Cetak semula' : 'Cetak';
    $advancedActive = $statusFilter !== 'pending'
        || $reviewFilter !== 'all'
        || filled($filters['requested_by'] ?? null);
@endphp
<div
    class="entry-print-station"
    data-entry-print-station
    data-jobs-url="{{ route('entry.print-jobs.index') }}"
    data-status-url="{{ route('entry.print-jobs.status') }}"
    data-batch-print-url="{{ route('entry.print-jobs.batch-print') }}"
    data-requested-by="{{ $filters['requested_by'] }}"
    data-status-filter="{{ $statusFilter }}"
    data-review-filter="{{ $reviewFilter }}"
    data-q="{{ $filters['q'] }}"
>
    <div class="entry-print-station__intro">
        <div>
            <h1 class="entry-page-title">Stesen cetak</h1>
            <p class="entry-page-lede">Job label dari operator Entry. Tapis, tandakan, lalu cetak atau cetak semula.</p>
        </div>
        <p class="entry-print-station__count">
            <span data-print-station-count>{{ $jobs->count() }}</span>
            <span data-print-station-count-label>{{ $countLabel }}</span>
            @if ($statusFilter !== 'pending')
                <span class="entry-print-station__count-pending">· <span data-print-station-pending-count>{{ $pendingCount }}</span> menunggu</span>
            @endif
        </p>
    </div>

    <div class="entry-print-station__toolbar">
        <form class="entry-print-station__filterbar" method="GET" action="{{ route('entry.print-station') }}">
            <input type="hidden" name="status" value="{{ $statusFilter }}" data-print-station-mobile-filter>
            <input type="hidden" name="review" value="{{ $reviewFilter }}" data-print-station-mobile-filter>
            <input type="hidden" name="requested_by" value="{{ $filters['requested_by'] }}" data-print-station-mobile-filter>

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
                    data-print-station-search
                >
            </label>

            <button
                class="entry-searchbar__funnel entry-print-station__mobile-only {{ $advancedActive ? 'is-active' : '' }}"
                type="button"
                data-modal-open="entry-print-station-filters"
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

            <label class="entry-print-station__inline-filter">
                <span>Status</span>
                <select name="status" data-print-station-status-filter data-print-station-desktop-filter>
                    <option value="pending" @selected($statusFilter === 'pending')>Belum dicetak</option>
                    <option value="printed" @selected($statusFilter === 'printed')>Dicetak</option>
                    <option value="all" @selected($statusFilter === 'all')>Semua</option>
                </select>
            </label>

            <label class="entry-print-station__inline-filter">
                <span>Pengesahan</span>
                <select name="review" data-print-station-review-filter data-print-station-desktop-filter>
                    <option value="all" @selected($reviewFilter === 'all')>Semua</option>
                    <option value="verified" @selected($reviewFilter === 'verified')>Disahkan</option>
                    <option value="unverified" @selected($reviewFilter === 'unverified')>Belum disahkan</option>
                </select>
            </label>

            <label class="entry-print-station__inline-filter">
                <span>Operator</span>
                <select name="requested_by" data-print-station-operator data-print-station-desktop-filter>
                    <option value="">Semua operator</option>
                    @foreach ($operators as $operator)
                        <option value="{{ $operator->id }}" @selected((string) $filters['requested_by'] === (string) $operator->id)>
                            {{ $operator->name }}
                        </option>
                    @endforeach
                </select>
            </label>
        </form>

        @if ($advancedActive || filled($filters['q']))
            <p class="entry-searchbar__hint entry-print-station__mobile-only">
                Penapis aktif.
                <a href="{{ route('entry.print-station') }}">Reset semua</a>
            </p>
        @endif

        <div class="entry-print-station__actions">
            <button
                class="entry-print-station__icon-btn"
                type="button"
                data-print-station-refresh
                aria-label="Muat semula"
                title="Muat semula"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0 1 12.7-5.4M19.5 12a7.5 7.5 0 0 1-12.7 5.4"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.2 4.8V8.5h-3.7M7.8 19.2v-3.7h3.7"/>
                </svg>
            </button>
            <button
                class="btn-secondary"
                type="button"
                data-print-station-mark-pending
                title="Tanda pilihan sebagai belum dicetak"
                disabled
            >
                Tanda belum
            </button>
            <button
                class="btn-secondary"
                type="button"
                data-print-station-mark-printed
                title="Tanda pilihan sebagai dicetak"
                disabled
            >
                Tanda dicetak
            </button>
            <button
                class="btn-primary"
                type="button"
                data-print-station-print-selected
                disabled
            >
                {{ $printSelectedLabel }}
            </button>
        </div>
    </div>

    <p class="entry-print-station__status hidden" data-print-station-status role="status"></p>

    <section class="entry-print-station__list" data-print-station-list aria-live="polite">
        @if ($jobs->isEmpty())
            <div class="entry-empty" data-print-station-empty>
                <p class="entry-empty__title">
                    {{ $statusFilter === 'printed' ? 'Tiada job dicetak' : ($statusFilter === 'all' ? 'Tiada job' : 'Tiada job menunggu') }}
                </p>
                <p class="entry-empty__text">Apabila operator tekan Hantar ke stesen, job akan muncul di sini.</p>
            </div>
        @else
            <div class="entry-print-station__cards" data-print-station-cards>
                <div class="entry-print-station__select-bar">
                    <label class="entry-print-station__select-all">
                        <input type="checkbox" data-print-station-select-all aria-label="Pilih semua">
                        <span>Pilih semua</span>
                    </label>
                </div>

                @foreach ($jobs as $job)
                    @php
                        $printUrl = route('entry.files.label', ['file' => $job->legal_file_id, 'type' => $job->type], absolute: false);
                        $previewUrl = $printUrl.(str_contains($printUrl, '?') ? '&' : '?').'embed=1';
                        $pending = $job->isPending();
                        $typeLabel = $job->type === 'side' ? 'Sisi' : 'Hadapan';
                        $ref = $job->legalFile?->reference_number ?? '—';
                        $operator = $job->requester?->name ?? '—';
                        $previewTitle = $ref.' · '.$typeLabel;
                        $deleteUrl = route('entry.print-jobs.destroy', $job, absolute: false);
                        $scan = $job->legalFile?->entryScan;
                        $reviewStatus = $scan?->review_status ?: App\Models\EntryScan::REVIEW_PENDING;
                        $reviewLabel = $scan?->reviewStatusLabel() ?? 'Belum semak';
                    @endphp
                    <article
                        class="entry-print-station__card"
                        data-print-job-id="{{ $job->id }}"
                        data-job-status="{{ $job->status }}"
                        data-print-url="{{ $printUrl }}"
                        data-preview-url="{{ $previewUrl }}"
                        data-preview-title="{{ $previewTitle }}"
                        data-delete-url="{{ $deleteUrl }}"
                    >
                        <label class="entry-print-station__check">
                            <input
                                type="checkbox"
                                data-print-station-select
                                value="{{ $job->id }}"
                                aria-label="Pilih {{ $ref }}"
                            >
                        </label>

                        <div class="entry-print-station__card-body">
                            <div class="entry-print-station__card-head">
                                <p class="entry-print-station__ref">{{ $ref }}</p>
                                <span class="entry-print-station__badge entry-print-station__badge--{{ $pending ? 'pending' : 'printed' }}">
                                    {{ $job->statusLabel() }}
                                </span>
                            </div>
                            <p class="entry-print-station__card-meta">
                                {{ $operator }} · {{ $typeLabel }}
                                · <span class="entry-ops__badge entry-ops__badge--review-{{ $reviewStatus }}">{{ $reviewLabel }}</span>
                            </p>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="entry-print-station__table-wrap">
                <table class="entry-print-station__table">
                    <thead>
                        <tr>
                            <th class="entry-print-station__check" scope="col">
                                <input type="checkbox" data-print-station-select-all aria-label="Pilih semua">
                            </th>
                            <th scope="col">Rujukan</th>
                            <th scope="col">Status</th>
                            <th scope="col">Pengesahan</th>
                            <th scope="col">Operator</th>
                            <th scope="col">Jenis</th>
                            <th scope="col">Dihantar</th>
                            <th scope="col">Dicetak</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($jobs as $job)
                            @php
                                $printUrl = route('entry.files.label', ['file' => $job->legal_file_id, 'type' => $job->type], absolute: false);
                                $previewUrl = $printUrl.(str_contains($printUrl, '?') ? '&' : '?').'embed=1';
                                $pending = $job->isPending();
                                $typeLabel = $job->type === 'side' ? 'Sisi' : 'Hadapan';
                                $ref = $job->legalFile?->reference_number ?? '—';
                                $previewTitle = $ref.' · '.$typeLabel;
                                $scan = $job->legalFile?->entryScan;
                                $reviewStatus = $scan?->review_status ?: App\Models\EntryScan::REVIEW_PENDING;
                                $reviewLabel = $scan?->reviewStatusLabel() ?? 'Belum semak';
                            @endphp
                            <tr
                                data-print-job-id="{{ $job->id }}"
                                data-job-status="{{ $job->status }}"
                                data-print-url="{{ $printUrl }}"
                                data-preview-url="{{ $previewUrl }}"
                                data-preview-title="{{ $previewTitle }}"
                                data-delete-url="{{ route('entry.print-jobs.destroy', $job, absolute: false) }}"
                            >
                                <td class="entry-print-station__check">
                                    <input
                                        type="checkbox"
                                        data-print-station-select
                                        value="{{ $job->id }}"
                                        aria-label="Pilih {{ $ref }}"
                                    >
                                </td>
                                <td>
                                    <span class="entry-print-station__ref">{{ $ref }}</span>
                                </td>
                                <td>
                                    <span class="entry-print-station__badge entry-print-station__badge--{{ $pending ? 'pending' : 'printed' }}">
                                        {{ $job->statusLabel() }}
                                    </span>
                                </td>
                                <td>
                                    <span class="entry-ops__badge entry-ops__badge--review-{{ $reviewStatus }}">
                                        {{ $reviewLabel }}
                                    </span>
                                </td>
                                <td>{{ $job->requester?->name ?? '—' }}</td>
                                <td>{{ $typeLabel }}</td>
                                <td class="entry-print-station__when-cell">
                                    {{ \App\Support\LocalDate::format($job->created_at) }}
                                </td>
                                <td class="entry-print-station__when-cell">
                                    {{ $job->printed_at ? \App\Support\LocalDate::format($job->printed_at) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

<div class="modal-overlay hidden" data-modal="entry-print-station-filters" aria-hidden="true">
    <div class="modal-panel entry-filter-modal" role="dialog" aria-modal="true" aria-labelledby="entry-print-station-filters-title">
        <div class="modal-header">
            <div>
                <p class="eyebrow text-amber-700">Stesen cetak</p>
                <h2 id="entry-print-station-filters-title" class="mt-1 text-xl font-semibold">Tapis lanjutan</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Tutup">Tutup</button>
        </div>

        <form class="entry-filter-modal__form" method="GET" action="{{ route('entry.print-station') }}">
            <input type="hidden" name="q" value="{{ $filters['q'] }}">

            <div class="entry-filter-modal__fields">
                <label class="entry-filters__field">
                    <span>Status</span>
                    <select name="status">
                        <option value="pending" @selected($statusFilter === 'pending')>Belum dicetak</option>
                        <option value="printed" @selected($statusFilter === 'printed')>Dicetak</option>
                        <option value="all" @selected($statusFilter === 'all')>Semua</option>
                    </select>
                </label>

                <label class="entry-filters__field">
                    <span>Pengesahan</span>
                    <select name="review">
                        <option value="all" @selected($reviewFilter === 'all')>Semua</option>
                        <option value="verified" @selected($reviewFilter === 'verified')>Disahkan</option>
                        <option value="unverified" @selected($reviewFilter === 'unverified')>Belum disahkan</option>
                    </select>
                </label>

                <label class="entry-filters__field">
                    <span>Operator</span>
                    <select name="requested_by">
                        <option value="">Semua operator</option>
                        @foreach ($operators as $operator)
                            <option value="{{ $operator->id }}" @selected((string) $filters['requested_by'] === (string) $operator->id)>
                                {{ $operator->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="entry-filter-modal__actions">
                <a class="btn-secondary w-full text-center" href="{{ route('entry.print-station', ['q' => $filters['q'] ?: null]) }}">Reset</a>
                <button class="btn-primary w-full" type="submit">Guna tapis</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay hidden" data-modal="entry-print-preview" aria-hidden="true">
    <div class="modal-panel entry-print-preview-panel max-w-3xl" role="dialog" aria-modal="true" aria-labelledby="entry-print-preview-title">
        <div class="modal-header">
            <div class="min-w-0">
                <p class="eyebrow text-amber-700">Pratonton label</p>
                <h2 id="entry-print-preview-title" class="mt-1 break-words text-xl font-semibold" data-print-preview-title>
                    Label
                </h2>
            </div>
            <button class="modal-close shrink-0" type="button" data-modal-close aria-label="Tutup">Tutup</button>
        </div>
        <div class="entry-print-preview__frame-wrap">
            <iframe
                class="entry-print-preview__frame"
                data-print-preview-frame
                title="Pratonton label"
            ></iframe>
        </div>
        <div class="entry-print-preview__footer mt-4 flex flex-wrap items-center justify-between gap-3">
            <button class="btn-secondary entry-print-preview__delete" type="button" data-print-preview-delete>
                Padam
            </button>
            <div class="entry-print-preview__actions">
                <button class="btn-secondary" type="button" data-print-preview-mark-status>
                    Tanda dicetak
                </button>
                <button class="btn-primary" type="button" data-print-preview-print>Cetak</button>
            </div>
        </div>
    </div>
</div>
@endsection
