@php
    /** @var \App\Models\LegalFile $file */
    /** @var string $qr */
    /** @var \App\Models\EntryScan|null $scan */
    /** @var \Illuminate\Support\Collection<int, \App\Models\CaseCategory>|null $categories */
    $scan = $scan ?? null;
    $categories = $categories ?? collect();
    $hasDocument = $scan
        && is_string($scan->document_path)
        && $scan->document_path !== '';
    $canEdit = $scan !== null && $categories->isNotEmpty();
    $openLabel = (string) session('open_file_label') === (string) $file->id
        || (string) old('_legal_file_id') === (string) $file->id;
    $initialType = $openLabel && $canEdit ? 'data' : 'front';
    $tabCount = 2 + ($canEdit ? 1 : 0) + ($hasDocument ? 1 : 0);
    $typesClass = match ($tabCount) {
        4 => ' file-label-modal__types--four',
        3 => ' file-label-modal__types--with-document',
        default => '',
    };
    $suggestion = [
        'reference_number' => $file->reference_number,
        'case_type' => $file->case_type,
        'case_category_id' => $file->case_category_id,
        'purchaser' => $file->purchaser,
        'vendor' => $file->vendor,
        'bank' => $file->bank,
        'property' => $file->property,
        'title_number' => $file->title_number,
        'suite_number' => $file->suite_number,
        'ic_number' => $file->ic_number,
        'start_date' => $file->start_date?->format('Y-m-d'),
        'end_date' => $file->end_date?->format('Y-m-d'),
        'borrowers' => $file->relationLoaded('parties')
            ? $file->parties->map(fn ($party) => [
                'name' => $party->name,
                'ic_number' => $party->ic_number,
            ])->values()->all()
            : [],
    ];
    $selectedType = old('case_type', $file->case_type) ?: App\Models\LegalFile::TYPE_SNP;
    if ((string) old('_legal_file_id') !== (string) $file->id) {
        $selectedType = $file->case_type ?: App\Models\LegalFile::TYPE_SNP;
    }
@endphp
<div
    class="modal-overlay{{ $openLabel ? '' : ' hidden' }}"
    data-modal="file-label-{{ $file->id }}"
    aria-hidden="{{ $openLabel ? 'false' : 'true' }}"
>
    <div
        class="modal-panel file-label-modal max-w-3xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="file-label-title-{{ $file->id }}"
        data-file-label-modal
        data-print-url="{{ route('entry.files.label', $file) }}"
        data-pdf-url="{{ route('entry.files.label.pdf', $file) }}"
        data-print-queue-url="{{ route('entry.print-jobs.store') }}"
        data-legal-file-id="{{ $file->id }}"
        data-file-label-initial-type="{{ $initialType }}"
    >
        <div class="file-label-modal__top">
            <div class="modal-header">
                <div class="min-w-0">
                    <p class="eyebrow text-amber-700">Label fail</p>
                    <h2 id="file-label-title-{{ $file->id }}" class="mt-1 break-words text-xl font-semibold sm:text-2xl">
                        {{ $file->reference_number }}
                    </h2>
                    <p class="file-label-modal__dims" data-file-label-dims>
                        <span @class(['hidden' => $initialType !== 'front']) data-file-label-dims-for="front">Hadapan · 150 × 100 mm</span>
                        <span @class(['hidden' => $initialType !== 'side']) data-file-label-dims-for="side">Sisi · 80 × 40 mm</span>
                        @if ($canEdit)
                            <span @class(['hidden' => $initialType !== 'data']) data-file-label-dims-for="data">Maklumat imbasan</span>
                        @endif
                        @if ($hasDocument)
                            <span @class(['hidden' => $initialType !== 'document']) data-file-label-dims-for="document">Gambar asal imbasan</span>
                        @endif
                    </p>
                </div>
                <button class="modal-close shrink-0" type="button" data-modal-close aria-label="Tutup">Tutup</button>
            </div>

            <style>@include('files.labels.styles')</style>

            <div class="file-label-modal__controls" role="group" aria-label="Jenis pratonton">
                <p class="file-label-modal__legend">Jenis pratonton</p>
                <div class="file-label-modal__types{{ $typesClass }}">
                    <button type="button" @class(['file-label-modal__type', 'is-active' => $initialType === 'front']) data-file-label-type="front">Hadapan</button>
                    <button type="button" @class(['file-label-modal__type', 'is-active' => $initialType === 'side']) data-file-label-type="side">Sisi</button>
                    @if ($canEdit)
                        <button type="button" @class(['file-label-modal__type', 'is-active' => $initialType === 'data']) data-file-label-type="data">Maklumat</button>
                    @endif
                    @if ($hasDocument)
                        <button type="button" @class(['file-label-modal__type', 'is-active' => $initialType === 'document']) data-file-label-type="document">Dokumen</button>
                    @endif
                </div>
            </div>
        </div>

        <div
            @class([
                'file-label-modal__preview-wrap',
                'file-label-modal__preview-wrap--form' => $initialType === 'data',
            ])
            data-file-label-preview-wrap
        >
            <div @class(['file-label-modal__preview', 'hidden' => $initialType !== 'front']) data-file-label-preview="front">
                <div class="file-label-preview-scale">
                    @include('files.labels.front', ['file' => $file, 'qr' => $qr])
                </div>
            </div>
            <div @class(['file-label-modal__preview', 'hidden' => $initialType !== 'side']) data-file-label-preview="side">
                <div class="file-label-preview-scale">
                    @include('files.labels.side', ['file' => $file, 'qr' => $qr])
                </div>
            </div>
            @if ($canEdit)
                <div @class(['file-label-modal__preview', 'file-label-modal__preview--data', 'hidden' => $initialType !== 'data']) data-file-label-preview="data">
                    <form
                        class="file-label-modal__edit-form"
                        method="POST"
                        action="{{ route('entry.scan.update', $scan) }}"
                        data-case-type-form
                        data-file-label-edit-form
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_legal_file_id" value="{{ $file->id }}">
                        @include('entry.partials.case-form-fields', [
                            'categories' => $categories,
                            'suggestion' => $suggestion,
                            'selectedType' => $selectedType,
                            'useOld' => (string) old('_legal_file_id') === (string) $file->id,
                            'layout' => 'modal',
                        ])
                        <button class="btn-primary min-h-12 w-full" type="submit">Simpan perubahan</button>
                    </form>
                </div>
            @endif
            @if ($hasDocument)
                <div @class(['file-label-modal__preview', 'file-label-modal__preview--document', 'hidden' => $initialType !== 'document']) data-file-label-preview="document">
                    <img
                        alt="Gambar asal imbasan {{ $file->reference_number }}"
                        data-file-label-document
                        data-src="{{ route('entry.scan.document', $scan) }}"
                    >
                </div>
            @endif
            <p @class(['file-label-modal__hint', 'hidden' => ! in_array($initialType, ['front', 'side'], true)]) data-file-label-hint-for="label">Pratonton · saiz sebenar semasa cetak / PDF</p>
            @if ($canEdit)
                <p @class(['file-label-modal__hint', 'hidden' => $initialType !== 'data']) data-file-label-hint-for="data">Semak &amp; kemas kini data dari imbasan</p>
            @endif
            @if ($hasDocument)
                <p @class(['file-label-modal__hint', 'hidden' => $initialType !== 'document']) data-file-label-hint-for="document">Gambar diambil semasa imbasan · untuk semakan semula</p>
            @endif
        </div>

        <div class="file-label-modal__bottom">
            <div class="file-label-modal__actions" data-file-label-actions>
                @if ($scan)
                    <button
                        class="btn-danger"
                        type="button"
                        data-modal-open="delete-entry-scan-{{ $scan->id }}"
                        data-file-label-delete
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
