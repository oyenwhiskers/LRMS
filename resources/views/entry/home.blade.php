@extends('layouts.entry')

@section('title', 'Utama')

@section('content')
<div class="entry-home">
    <section class="entry-stat" aria-label="Jumlah fail">
        <p class="entry-stat__label">Fail disimpan</p>
        <p class="entry-stat__value">{{ $storedCount }}</p>
        <p class="entry-stat__hint">Daftar &amp; disimpan oleh anda</p>
    </section>

    <section class="entry-list">
        <div class="entry-list__head">
            <h2>Terbaru</h2>
            @if ($recent->isNotEmpty())
                <a href="{{ route('entry.history') }}">Lihat semua</a>
            @endif
        </div>

        @forelse ($recent as $scan)
            @if ($scan->legalFile)
                <button
                    type="button"
                    class="entry-card entry-card--action"
                    data-modal-open="file-label-{{ $scan->legalFile->id }}"
                >
                    <div class="entry-card__body">
                        <p class="entry-card__title">{{ $scan->legalFile->reference_number }}</p>
                        <p class="entry-card__meta">{{ $scan->legalFile->purchaser ?: '—' }}</p>
                        @if ($scan->shelf)
                            <p class="entry-card__shelf">{{ $scan->shelf->full_location }}</p>
                        @endif
                    </div>
                    <span class="entry-pill">{{ $scan->statusLabel() }}</span>
                </button>
            @else
                <article class="entry-card">
                    <div class="entry-card__body">
                        <p class="entry-card__title">Draf</p>
                        <p class="entry-card__meta">Belum disimpan</p>
                    </div>
                    <span class="entry-pill">{{ $scan->statusLabel() }}</span>
                </article>
            @endif
        @empty
            <div class="entry-empty">
                <div class="entry-empty__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V5.5A1.5 1.5 0 019.5 4h5A1.5 1.5 0 0116 5.5V7M6.5 7h11A1.5 1.5 0 0119 8.5v9A1.5 1.5 0 0117.5 19h-11A1.5 1.5 0 015 17.5v-9A1.5 1.5 0 016.5 7zM9 12h6"/></svg>
                </div>
                <p class="entry-empty__title">Belum ada fail</p>
                <p class="entry-empty__text">Guna tab Imbas untuk mula mendaftar kes.</p>
            </div>
        @endforelse
    </section>
</div>

@foreach ($recent as $scan)
    @if ($scan->legalFile && isset($labelQrs[$scan->legalFile->id]))
        @include('entry.partials.file-label-modal', [
            'file' => $scan->legalFile,
            'qr' => $labelQrs[$scan->legalFile->id],
            'scan' => $scan,
            'categories' => $categories,
        ])
    @endif
@endforeach
@endsection
