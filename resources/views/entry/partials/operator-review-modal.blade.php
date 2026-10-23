@php
    /** @var \App\Models\LegalFile $file */
    /** @var \App\Models\EntryScan $scan */
    /** @var \Illuminate\Support\Collection<int, \App\Models\CaseCategory> $categories */
    $hasDocument = is_string($scan->document_path) && $scan->document_path !== '';
    $shelf = $scan->shelf;
    $rack = $shelf?->rack;
    $onShelf = $scan->status === App\Models\EntryScan::STATUS_STORED && $shelf;
    $tabCount = 2 + ($hasDocument ? 1 : 0);
    $typesClass = $tabCount >= 3 ? ' file-label-modal__types--with-document' : '';
    $initialType = 'data';
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
    $selectedType = $file->case_type ?: App\Models\LegalFile::TYPE_SNP;
@endphp
<div
    class="modal-overlay hidden"
    data-modal="operator-review-{{ $scan->id }}"
    aria-hidden="true"
>
    <div
        class="modal-panel file-label-modal max-w-3xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="operator-review-title-{{ $scan->id }}"
        data-file-label-modal
        data-file-label-initial-type="{{ $initialType }}"
    >
        <div class="file-label-modal__top">
            <div class="modal-header">
                <div class="min-w-0">
                    <p class="eyebrow text-amber-700">Semakan operator</p>
                    <h2 id="operator-review-title-{{ $scan->id }}" class="mt-1 break-words text-xl font-semibold sm:text-2xl">
                        {{ $file->reference_number }}
                    </h2>
                    <p class="file-label-modal__dims" data-file-label-dims>
                        <span data-file-label-dims-for="data">
                            {{ $scan->user?->name ?: '—' }} · maklumat pendaftaran
                        </span>
                        <span class="hidden" data-file-label-dims-for="shelf">
                            @if ($onShelf)
                                {{ $shelf->full_location }}
                            @else
                                Belum di rak
                            @endif
                        </span>
                        @if ($hasDocument)
                            <span class="hidden" data-file-label-dims-for="document">Gambar asal imbasan</span>
                        @endif
                    </p>
                </div>
                <button class="modal-close shrink-0" type="button" data-modal-close aria-label="Tutup">Tutup</button>
            </div>

            <div class="file-label-modal__controls" role="group" aria-label="Jenis pratonton">
                <p class="file-label-modal__legend">Jenis pratonton</p>
                <div class="file-label-modal__types{{ $typesClass }}">
                    <button type="button" class="file-label-modal__type is-active" data-file-label-type="data">Maklumat</button>
                    <button type="button" class="file-label-modal__type" data-file-label-type="shelf">Rak &amp; shelf</button>
                    @if ($hasDocument)
                        <button type="button" class="file-label-modal__type" data-file-label-type="document">Dokumen</button>
                    @endif
                </div>
            </div>
        </div>

        <div
            class="file-label-modal__preview-wrap file-label-modal__preview-wrap--form"
            data-file-label-preview-wrap
        >
            <div class="file-label-modal__preview file-label-modal__preview--data" data-file-label-preview="data">
                <fieldset class="file-label-modal__edit-form" disabled>
                    @include('entry.partials.case-form-fields', [
                        'categories' => $categories,
                        'suggestion' => $suggestion,
                        'selectedType' => $selectedType,
                        'useOld' => false,
                        'layout' => 'modal',
                        'readonly' => true,
                    ])
                </fieldset>
            </div>

            <div class="file-label-modal__preview file-label-modal__preview--shelf hidden" data-file-label-preview="shelf">
                <dl class="entry-ops-shelf">
                    <div>
                        <dt>Status</dt>
                        <dd>
                            <span class="entry-ops__badge entry-ops__badge--{{ $scan->status }}">
                                {{ $scan->statusLabel() }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt>Rak</dt>
                        <dd>
                            @if ($rack)
                                {{ $rack->code }}{{ $rack->name ? ' — '.$rack->name : '' }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Shelf</dt>
                        <dd>
                            @if ($shelf)
                                {{ $shelf->code }}{{ $shelf->name ? ' — '.$shelf->name : '' }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Lokasi penuh</dt>
                        <dd>
                            @if ($onShelf)
                                {{ $shelf->full_location }}
                            @else
                                Belum di rak · tunggu imbas QR
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            @if ($hasDocument)
                <div class="file-label-modal__preview file-label-modal__preview--document hidden" data-file-label-preview="document">
                    <img
                        alt="Gambar asal imbasan {{ $file->reference_number }}"
                        data-file-label-document
                        data-src="{{ route('entry.operators.document', $scan) }}"
                    >
                </div>
            @endif
            <p class="file-label-modal__hint" data-file-label-hint-for="data">Baca sahaja · untuk semakan silang</p>
            <p class="file-label-modal__hint hidden" data-file-label-hint-for="shelf">Lokasi simpanan fail di rak / shelf</p>
            @if ($hasDocument)
                <p class="file-label-modal__hint hidden" data-file-label-hint-for="document">Gambar diambil semasa imbasan · untuk semakan semula</p>
            @endif
        </div>
    </div>
</div>
