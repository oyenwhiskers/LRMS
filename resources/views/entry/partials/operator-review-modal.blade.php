@php
    /** @var \App\Models\LegalFile $file */
    /** @var \App\Models\EntryScan $scan */
    /** @var \Illuminate\Support\Collection<int, \App\Models\CaseCategory> $categories */
    $documentPaths = $scan->documentPaths();
    $hasDocument = $documentPaths !== [];
    $documentUrls = array_map(
        fn (int $index): string => route('entry.operators.document', $scan).'?page='.($index + 1),
        array_keys($documentPaths),
    );
    $shelf = $scan->shelf;
    $rack = $shelf?->rack;
    $onShelf = $scan->status === App\Models\EntryScan::STATUS_STORED && $shelf;
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
        class="modal-panel file-label-modal operator-review-modal{{ $hasDocument ? ' operator-review-modal--split' : '' }}"
        role="dialog"
        aria-modal="true"
        aria-labelledby="operator-review-title-{{ $scan->id }}"
        data-file-label-modal
        data-file-label-initial-type="data"
        @if ($hasDocument)
            data-operator-review-tabs
            data-operator-review-tab="maklumat"
        @endif
    >
        <div class="operator-review-page">
            <div class="operator-review-page__chrome">
                <div class="operator-review-page__header">
                    <div class="min-w-0">
                        <p class="eyebrow text-amber-700">Semakan operator</p>
                        <h2 id="operator-review-title-{{ $scan->id }}" class="mt-1 break-words text-xl font-semibold sm:text-2xl">
                            {{ $file->reference_number }}
                        </h2>
                        <p class="file-label-modal__dims">
                            {{ $scan->user?->name ?: '—' }} · maklumat &amp; lokasi
                            @if ($hasDocument)
                                · gambar imbasan
                            @endif
                        </p>
                    </div>
                    <button class="modal-close shrink-0" type="button" data-modal-close aria-label="Tutup">Tutup</button>
                </div>

                @if ($hasDocument)
                    <div class="operator-review-page__tabs" role="tablist" aria-label="Pratonton">
                        <button
                            type="button"
                            class="operator-review-page__tab is-active"
                            role="tab"
                            aria-selected="true"
                            data-operator-review-tab-btn="maklumat"
                        >Maklumat</button>
                        <button
                            type="button"
                            class="operator-review-page__tab"
                            role="tab"
                            aria-selected="false"
                            data-operator-review-tab-btn="dokumen"
                        >Dokumen</button>
                    </div>
                @endif
            </div>

            <div class="operator-review-page__body">
                <div class="operator-review-page__left" data-operator-review-pane="maklumat">
                    <div class="operator-review-page__info">
                        <section class="operator-review-page__section" aria-labelledby="operator-review-maklumat-{{ $scan->id }}">
                            <h3 id="operator-review-maklumat-{{ $scan->id }}" class="operator-review-page__section-title">Maklumat</h3>
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
                        </section>

                        <section class="operator-review-page__section" aria-labelledby="operator-review-shelf-{{ $scan->id }}">
                            <div class="operator-review-page__section-head">
                                <h3 id="operator-review-shelf-{{ $scan->id }}" class="operator-review-page__section-title">Rak &amp; shelf</h3>
                                <div class="operator-review-page__badges">
                                    <span class="entry-ops__badge entry-ops__badge--{{ $scan->status }}">
                                        {{ $scan->statusLabel() }}
                                    </span>
                                    <span class="entry-ops__badge entry-ops__badge--review-{{ $scan->review_status ?: App\Models\EntryScan::REVIEW_PENDING }}">
                                        {{ $scan->reviewStatusLabel() }}
                                    </span>
                                </div>
                            </div>
                            <dl class="entry-ops-shelf entry-ops-shelf--review">
                                <div>
                                    <dt>Rak</dt>
                                    <dd>
                                        @if ($rack)
                                            <span class="entry-ops-shelf__code">{{ $rack->code }}</span>
                                            @if ($rack->name)
                                                <span class="entry-ops-shelf__name">{{ $rack->name }}</span>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt>Shelf</dt>
                                    <dd>
                                        @if ($shelf)
                                            <span class="entry-ops-shelf__code">{{ $shelf->code }}</span>
                                            @if ($shelf->name)
                                                <span class="entry-ops-shelf__name">{{ $shelf->name }}</span>
                                            @endif
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
                        </section>
                    </div>
                </div>

                @if ($hasDocument)
                    <div
                        class="operator-review-page__right"
                        data-operator-review-pane="dokumen"
                        data-doc-zoom
                        data-doc-pages='@json($documentUrls)'
                    >
                        <div class="operator-review-doc-zoom__toolbar" role="group" aria-label="Zum dokumen">
                            <span class="operator-review-doc-zoom__caption">Dokumen</span>
                            @if (count($documentUrls) > 1)
                                <div class="operator-review-doc-pages" role="group" aria-label="Muka surat">
                                    <button type="button" class="operator-review-doc-zoom__btn" data-doc-page-prev aria-label="Muka sebelumnya">‹</button>
                                    <span class="operator-review-doc-pages__label" data-doc-page-label>1 / {{ count($documentUrls) }}</span>
                                    <button type="button" class="operator-review-doc-zoom__btn" data-doc-page-next aria-label="Muka seterusnya">›</button>
                                </div>
                            @endif
                            <button type="button" class="operator-review-doc-zoom__btn" data-doc-zoom-out aria-label="Zum keluar">−</button>
                            <span class="operator-review-doc-zoom__label" data-doc-zoom-label>Muat</span>
                            <button type="button" class="operator-review-doc-zoom__btn" data-doc-zoom-in aria-label="Zum masuk">+</button>
                            <button type="button" class="operator-review-doc-zoom__btn operator-review-doc-zoom__btn--reset" data-doc-zoom-reset>Reset</button>
                        </div>
                        <div class="operator-review-doc-zoom__viewport" data-doc-zoom-viewport>
                            <img
                                class="operator-review-doc-zoom__img"
                                alt="Gambar asal imbasan {{ $file->reference_number }}"
                                data-file-label-document
                                data-doc-zoom-img
                                data-src="{{ $documentUrls[0] }}"
                                draggable="false"
                            >
                        </div>
                    </div>
                @endif
            </div>

            <div class="operator-review-page__actions">
                <label class="operator-review-page__notes">
                    <span class="operator-review-page__notes-label">Catatan pembetulan</span>
                    <textarea
                        class="form-input"
                        form="operator-correction-{{ $scan->id }}"
                        name="review_notes"
                        rows="3"
                        maxlength="1000"
                        required
                        placeholder="Apa yang perlu dibetulkan oleh operator?"
                    >{{ old('review_notes', $scan->review_notes) }}</textarea>
                </label>
                <div class="operator-review-page__action-row">
                    <form
                        id="operator-correction-{{ $scan->id }}"
                        method="POST"
                        action="{{ route('entry.operators.review', $scan) }}"
                    >
                        @csrf
                        <input type="hidden" name="review_status" value="{{ App\Models\EntryScan::REVIEW_NEEDS_CORRECTION }}">
                        <button
                            class="btn-secondary"
                            type="submit"
                        >Perlu pembetulan</button>
                    </form>
                    <form method="POST" action="{{ route('entry.operators.review', $scan) }}">
                        @csrf
                        <input type="hidden" name="review_status" value="{{ App\Models\EntryScan::REVIEW_VERIFIED }}">
                        <button
                            class="btn-primary"
                            type="submit"
                            @disabled($scan->review_status === App\Models\EntryScan::REVIEW_VERIFIED)
                        >Tanda disahkan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
