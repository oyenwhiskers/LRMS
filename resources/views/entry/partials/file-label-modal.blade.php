@php
    /** @var \App\Models\LegalFile $file */
    /** @var string $qr */
    /** @var \App\Models\EntryScan|null $scan */
    $scan = $scan ?? null;
    $hasDocument = $scan
        && is_string($scan->document_path)
        && $scan->document_path !== '';
@endphp
<div class="modal-overlay hidden" data-modal="file-label-{{ $file->id }}" aria-hidden="true">
    <div
        class="modal-panel max-w-3xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="file-label-title-{{ $file->id }}"
        data-file-label-modal
        data-print-url="{{ route('entry.files.label', $file) }}"
        data-pdf-url="{{ route('entry.files.label.pdf', $file) }}"
        data-print-queue-url="{{ route('entry.print-jobs.store') }}"
        data-legal-file-id="{{ $file->id }}"
    >
        <div class="modal-header">
            <div class="min-w-0">
                <p class="eyebrow text-amber-700">Label fail</p>
                <h2 id="file-label-title-{{ $file->id }}" class="mt-1 break-words text-xl font-semibold sm:text-2xl">
                    {{ $file->reference_number }}
                </h2>
                <p class="file-label-modal__dims" data-file-label-dims>
                    <span data-file-label-dims-for="front">Hadapan · 150 × 100 mm</span>
                    <span class="hidden" data-file-label-dims-for="side">Sisi · 80 × 40 mm</span>
                    @if ($hasDocument)
                        <span class="hidden" data-file-label-dims-for="document">Gambar asal imbasan</span>
                    @endif
                </p>
            </div>
            <button class="modal-close shrink-0" type="button" data-modal-close aria-label="Tutup">Tutup</button>
        </div>

        <style>@include('files.labels.styles')</style>

        <div class="file-label-modal__controls" role="group" aria-label="Jenis pratonton">
            <p class="file-label-modal__legend">Jenis pratonton</p>
            <div class="file-label-modal__types{{ $hasDocument ? ' file-label-modal__types--with-document' : '' }}">
                <button type="button" class="file-label-modal__type is-active" data-file-label-type="front">Hadapan</button>
                <button type="button" class="file-label-modal__type" data-file-label-type="side">Sisi</button>
                @if ($hasDocument)
                    <button type="button" class="file-label-modal__type" data-file-label-type="document">Dokumen</button>
                @endif
            </div>
        </div>

        <div class="file-label-modal__preview-wrap">
            <div class="file-label-modal__preview" data-file-label-preview="front">
                <div class="file-label-preview-scale">
                    @include('files.labels.front', ['file' => $file, 'qr' => $qr])
                </div>
            </div>
            <div class="file-label-modal__preview hidden" data-file-label-preview="side">
                <div class="file-label-preview-scale">
                    @include('files.labels.side', ['file' => $file, 'qr' => $qr])
                </div>
            </div>
            @if ($hasDocument)
                <div class="file-label-modal__preview file-label-modal__preview--document hidden" data-file-label-preview="document">
                    <img
                        alt="Gambar asal imbasan {{ $file->reference_number }}"
                        data-file-label-document
                        data-src="{{ route('entry.scan.document', $scan) }}"
                    >
                </div>
            @endif
            <p class="file-label-modal__hint" data-file-label-hint-for="label">Pratonton · saiz sebenar semasa cetak / PDF</p>
            @if ($hasDocument)
                <p class="file-label-modal__hint hidden" data-file-label-hint-for="document">Gambar diambil semasa imbasan · untuk semakan semula</p>
            @endif
        </div>

            <div class="file-label-modal__actions">
            @if ($scan)
                <button
                    class="btn-danger"
                    type="button"
                    data-modal-open="delete-entry-scan-{{ $scan->id }}"
                >Padam</button>
            @endif
            <a class="btn-secondary" data-file-label-pdf href="{{ route('entry.files.label.pdf', ['file' => $file, 'type' => 'front']) }}">Muat turun PDF</a>
            <button
                class="btn-primary"
                type="button"
                data-file-label-print
                data-print-href="{{ route('entry.files.label', ['file' => $file, 'type' => 'front']) }}"
            >Hantar ke stesen</button>
        </div>
        <p class="file-label-modal__queue-status hidden" data-file-label-queue-status role="status"></p>
    </div>
</div>

@if ($scan)
    <div class="modal-overlay hidden" data-modal="delete-entry-scan-{{ $scan->id }}" aria-hidden="true">
        <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="delete-entry-scan-title-{{ $scan->id }}">
            <div class="modal-header">
                <div>
                    <p class="eyebrow text-amber-700">Padam fail</p>
                    <h2 id="delete-entry-scan-title-{{ $scan->id }}" class="mt-1 text-xl font-semibold">Padam rekod?</h2>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Tutup">Tutup</button>
            </div>
            <p class="mt-5 text-sm text-stone-600">
                Padam <strong>{{ $file->reference_number }}</strong>
                @if ($file->purchaser)
                    ({{ $file->purchaser }})
                @endif
                secara kekal? Tindakan ini tidak boleh dibatalkan.
            </p>
            <form class="mt-6 flex justify-end gap-3" method="POST" action="{{ route('entry.scan.destroy', $scan) }}">
                @csrf
                @method('DELETE')
                <button class="btn-secondary" type="button" data-modal-close>Batal</button>
                <button class="btn-danger" type="submit">Padam</button>
            </form>
        </div>
    </div>
@endif
