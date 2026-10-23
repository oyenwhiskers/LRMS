@extends('layouts.entry')

@section('title', 'Simpan di rak')

@section('content')
<div class="entry-shelf">
    <header class="entry-shelf__head">
        <p class="entry-stat__label">Langkah 3 / 3</p>
        <ol class="entry-shelf__steps" aria-label="Kemajuan">
            <li class="entry-shelf__step is-done" aria-hidden="true"><span>1</span></li>
            <li class="entry-shelf__step is-done" aria-hidden="true"><span>2</span></li>
            <li class="entry-shelf__step is-current"><span>3</span></li>
        </ol>
        <h1 class="entry-page-title">Imbas QR rak</h1>
        <p class="entry-page-lede">Imbas QR rak tempat fail ini diletakkan.</p>
    </header>

    <section class="entry-shelf__file" aria-label="Fail untuk disimpan">
        <p class="entry-shelf__file-label">Fail</p>
        <p class="entry-shelf__file-ref">{{ $scan->legalFile?->reference_number ?: '—' }}</p>
    </section>

    <form
        class="entry-shelf__form"
        method="POST"
        action="{{ route('entry.shelf.store', $scan) }}"
        data-entry-shelf
        data-auto-store
    >
        @csrf

        <button
            class="entry-shelf__scan"
            type="button"
            data-scan-target="shelf_scan"
            data-label-idle="Imbas QR rak"
            data-label-active="Tutup kamera"
        >
            <span class="entry-shelf__scan-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 4.5H5.25A1.75 1.75 0 003.5 6.25V8M17 4.5h1.75A1.75 1.75 0 0120.5 6.25V8M7 19.5H5.25A1.75 1.75 0 013.5 17.75V16M17 19.5h1.75a1.75 1.75 0 001.75-1.75V16M7.5 12h9"/></svg>
            </span>
            Imbas QR rak
        </button>

        <div id="qr-reader" class="entry-shelf__reader hidden"></div>

        <div class="entry-shelf__result hidden" data-shelf-result>
            <p class="entry-shelf__result-label">Rak dijumpai</p>
            <p class="entry-shelf__result-value" data-shelf-location></p>
        </div>

        <p
            id="scanner-status"
            class="entry-shelf__status"
            aria-live="polite"
            data-msg-invalid="Imbas QR rak yang sah."
            data-msg-checking="Semak rak…"
            data-msg-bad="QR rak tidak sah atau rak tidak aktif."
            data-msg-required="Imbas QR rak yang sah."
            data-msg-camera="Kamera aktif — letak QR dalam bingkai."
            data-msg-camera-stop="Kamera ditutup."
            data-msg-wrong="Jenis QR salah. Imbas QR rak."
            data-msg-camera-fail="Kamera tidak tersedia."
            data-msg-ok="Menyimpan…"
        ></p>

        {{-- Keep for camera / USB scanner wiring; not shown to avoid a second path. --}}
        <input
            id="shelf_scan"
            class="sr-only"
            data-qr-kind="shelf"
            data-lookup-url="{{ route('entry.shelves.lookup', ['identifier' => '__IDENTIFIER__']) }}"
            data-initial-qr="{{ old('shelf_qr') }}"
            autocomplete="off"
            tabindex="-1"
            aria-hidden="true"
        >

        <input id="shelf_qr" type="hidden" name="shelf_qr" value="{{ old('shelf_qr') }}">

        @error('shelf_qr')
            <p class="entry-shelf__error">{{ $message }}</p>
        @enderror
    </form>
</div>
@endsection
