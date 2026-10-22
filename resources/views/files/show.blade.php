@extends('layouts.app')

@section('title', $file->reference_number)
@section('back', route('files.index'))

@section('content')
<header class="flex flex-col gap-4">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="eyebrow text-amber-700">{{ $file->file_identifier }}</p>
            <h1 class="mt-1 text-3xl font-semibold">{{ $file->reference_number }}</h1>
            <p class="mt-2 text-stone-600">{{ $file->purchaser }}</p>
        </div>
        <a class="btn-secondary hidden shrink-0 lg:inline-flex" href="{{ route('files.index') }}">Back</a>
    </div>
    @canany(['files.update', 'labels.print', 'files.delete'])
        <div class="flex w-full items-stretch gap-2">
            @can('files.update')
                <a class="btn-secondary min-w-0 flex-1 justify-center" href="{{ route('files.edit', $file) }}">Edit</a>
            @endcan
            @can('labels.print')
                <button class="btn-primary min-w-0 flex-1 justify-center" type="button" data-modal-open="file-label-{{ $file->id }}">Print label</button>
            @endcan
            @can('files.delete')
                <button class="btn-danger min-w-0 flex-1 justify-center" type="button" data-modal-open="delete-file-{{ $file->id }}">Delete</button>
            @endcan
        </div>
    @endcanany
</header>

<div class="mt-8 grid gap-6 lg:grid-cols-3">
    <section class="border border-stone-200 bg-white p-6 lg:col-span-2">
        <h2 class="text-xl font-semibold">File information</h2>
        <dl class="mt-6 grid gap-5">
            @php
                $details = [
                    'Case type' => $file->caseTypeLabel(),
                ];

                if ($file->case_type === App\Models\LegalFile::TYPE_LOAN) {
                    $details['Bank'] = $file->bank;
                    $details['Property address'] = $file->property;
                    $details['Title number'] = $file->title_number;
                } elseif ($file->case_type === App\Models\LegalFile::TYPE_LITIGATION) {
                    $details['Suite number'] = $file->suite_number;
                    $details['IC'] = $file->ic_number;
                } else {
                    $details['Vendor'] = $file->vendor;
                    $details['Property address'] = $file->property;
                    $details['Title number'] = $file->title_number;
                }

                $details['Case category'] = $file->caseCategory
                    ? $file->caseCategory->code.' — '.$file->caseCategory->name
                    : $file->matter_type;
                $details['Start date'] = $file->start_date?->format('d M Y');
                $details['End date'] = $file->end_date?->format('d M Y');
                $details['Person responsible'] = $file->personInCharge?->full_name;
            @endphp
            @foreach($details as $label => $value)
                <div class="form-row">
                    <dt class="form-label form-row__label">{{ $label }}</dt>
                    <dd class="form-row__control text-sm font-medium">{{ $value ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>

        @php
            $relatedFiles = $file->relatedFiles();
        @endphp
        <div class="mt-8 border-t border-stone-200 pt-6">
            <h3 class="text-lg font-semibold">Related files</h3>
            @if($relatedFiles->isEmpty())
                <p class="mt-3 text-sm text-stone-500">No related files linked.</p>
            @else
                <ul class="mt-4 space-y-2">
                    @foreach($relatedFiles as $related)
                        <li>
                            <a class="font-semibold text-amber-800 hover:text-amber-700" href="{{ route('files.show', $related) }}">
                                {{ $related->reference_number }}
                            </a>
                            <span class="text-sm text-stone-500"> — {{ $related->purchaser }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <aside class="border border-stone-200 bg-stone-100 p-6">
        <h2 class="text-xl font-semibold">Status</h2>
        <dl class="mt-6 grid gap-5">
            <div class="form-row">
                <dt class="form-label form-row__label">File status</dt>
                <dd class="form-row__control">
                    <span class="status-badge status-{{ $file->statusTone() }}">{{ $file->statusLabel() }}</span>
                </dd>
            </div>
            <div class="form-row">
                <dt class="form-label form-row__label">Current holder</dt>
                <dd class="form-row__control font-semibold">{{ $file->currentHolder?->full_name ?? 'None' }}</dd>
            </div>
            <div class="form-row">
                <dt class="form-label form-row__label">Shelf</dt>
                <dd class="form-row__control font-semibold">{{ $file->shelf?->full_location ?? 'Not stored yet' }}</dd>
            </div>
        </dl>

        @if($file->status === \App\Models\LegalFile::STATUS_REGISTERED)
            @can('completions.request')
                <div class="mt-6 border-t border-stone-300 pt-5">
                    <h3 class="text-sm font-semibold text-stone-800">Completion</h3>
                    <p class="mt-2 text-sm text-stone-600">
                        Case is still active. Submit for boss review when the matter is finished.
                    </p>
                    <button
                        class="btn-primary mt-4 w-full"
                        type="button"
                        data-modal-open="request-completion-{{ $file->id }}"
                    >Request completion</button>
                </div>
            @endcan
        @endif

        @if($file->status === \App\Models\LegalFile::STATUS_PENDING)
            <div class="mt-6 border-t border-stone-300 pt-5">
                <h3 class="text-sm font-semibold text-stone-800">Completion review</h3>
                @can('completions.review')
                    <p class="mt-2 text-sm text-stone-600">
                        Awaiting boss approval. Approve to send this file to storage, or reject to return it to registered.
                    </p>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button
                            class="btn-danger w-full"
                            type="button"
                            data-modal-open="reject-completion-{{ $file->id }}"
                        >Reject</button>
                        <form method="POST" action="{{ route('completions.approve', $file) }}">
                            @csrf
                            <button class="btn-success w-full" type="submit">Approve</button>
                        </form>
                    </div>
                @else
                    <p class="mt-2 text-sm text-stone-600">
                        Waiting for boss review. The physical file should be checked before approval.
                    </p>
                @endcan
            </div>
        @endif

        @if($file->status === \App\Models\LegalFile::STATUS_COMPLETED)
            <div class="mt-6 border-t border-stone-300 pt-5">
                <h3 class="text-sm font-semibold text-stone-800">Ready for storage</h3>
                <p class="mt-2 text-sm text-stone-600">
                    This file is ready to be placed on a shelf. Use Store File to scan the file and shelf.
                </p>
                @can('movements.pic_intake')
                    <a class="btn-primary mt-4 inline-flex w-full justify-center" href="{{ route('movements.pic-intake', ['return_file' => $file->id]) }}">Go to Store File</a>
                @endcan
            </div>
        @endif

        <div class="mt-6 space-y-3">
            @can('missing.manage')
                @if($file->status !== \App\Models\LegalFile::STATUS_MISSING)
                    @if(in_array($file->status, [\App\Models\LegalFile::STATUS_STORED, \App\Models\LegalFile::STATUS_BORROWED], true))
                        <form method="POST" action="{{ route('files.missing', $file) }}">
                            @csrf
                            @method('PATCH')
                            <textarea class="form-input" name="notes" placeholder="Reason or circumstances" required></textarea>
                            <button class="btn-danger mt-2 w-full">Mark missing</button>
                        </form>
                    @endif
                @else
                    <form method="POST" action="{{ route('files.found', $file) }}">
                        @csrf
                        @method('PATCH')
                        <input class="form-input" name="notes" placeholder="Found notes">
                        <select class="form-input mt-2" name="shelf_id" required>
                            <option value="">Select restored shelf</option>
                            @foreach($shelves as $shelf)
                                <option value="{{ $shelf->id }}">{{ $shelf->full_location }}</option>
                            @endforeach
                        </select>
                        <button class="btn-primary mt-2 w-full">Mark found</button>
                    </form>
                @endif
            @endcan
        </div>
    </aside>
</div>

<section class="mt-8 border border-stone-200 bg-white p-4 sm:p-6">
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-xl font-semibold">Movement history</h2>
        @can('exports.movements')
            <a class="text-sm font-semibold text-amber-800" href="{{ route('exports.movements') }}">Export history</a>
        @endcan
    </div>

    {{-- Desktop table --}}
    <div class="mt-5 hidden overflow-x-auto lg:block">
        <table class="data-table history-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Movement</th>
                    <th>Person</th>
                    <th>Shelf</th>
                    <th>By</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movements as $movement)
                    <tr>
                        <td class="history-table__when">{{ $movement->occurred_at?->format('d M Y H:i') }}</td>
                        <td>{{ $movement->typeLabel() }}</td>
                        <td>{{ $movement->historyPersonLabel() ?: '—' }}</td>
                        <td>{{ $movement->historyShelfLabel() ?: '—' }}</td>
                        <td>{{ $movement->processor?->name ?? '—' }}</td>
                        <td class="whitespace-normal">{{ $movement->notes ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center text-stone-500">No history yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile timeline cards --}}
    <div class="mt-5 lg:hidden">
        @if($movements->isNotEmpty())
            <div class="mobile-list movement-history-mobile">
                @foreach($movements as $movement)
                    @php
                        $detailParts = array_values(array_filter([
                            $movement->historyPersonLabel(),
                            $movement->historyShelfLabel(),
                            $movement->processor?->name ? 'By '.$movement->processor->name : null,
                            $movement->notes ?: null,
                        ]));
                    @endphp
                    @include('layouts.partials.mobile-list-card', [
                        'title' => $movement->typeLabel(),
                        'meta' => $movement->occurred_at?->format('d M Y H:i'),
                        'detail' => $detailParts ? implode(' · ', $detailParts) : null,
                    ])
                @endforeach
            </div>
        @else
            <p class="py-10 text-center text-sm text-stone-500">No history yet.</p>
        @endif
    </div>

    <div class="mt-6">{{ $movements->links() }}</div>
</section>

@can('labels.print')
    <div class="modal-overlay hidden" data-modal="file-label-{{ $file->id }}" aria-hidden="true">
        <div
            class="modal-panel max-w-3xl"
            role="dialog"
            aria-modal="true"
            aria-labelledby="file-label-title-{{ $file->id }}"
            data-file-label-modal
            data-print-url="{{ route('files.label', $file) }}"
            data-pdf-url="{{ route('files.label.pdf', $file) }}"
        >
            <div class="modal-header">
                <div>
                    <p class="eyebrow text-amber-700">File label</p>
                    <h2 id="file-label-title-{{ $file->id }}" class="mt-1 text-2xl font-semibold">Front 100 × 150 mm (landscape) · Side 80 × 40 mm</h2>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
            </div>

            <style>@include('files.labels.styles')</style>

            <div class="file-label-modal__controls" role="group" aria-label="Label type">
                <p class="file-label-modal__legend">Label type</p>
                <div class="file-label-modal__types">
                    <button type="button" class="file-label-modal__type is-active" data-file-label-type="front">Front</button>
                    <button type="button" class="file-label-modal__type" data-file-label-type="side">Side</button>
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
            </div>

            <div class="mt-5 flex flex-wrap justify-end gap-3">
                <a class="btn-secondary" data-file-label-pdf href="{{ route('files.label.pdf', ['file' => $file, 'type' => 'front']) }}">Download PDF</a>
                <button
                    class="btn-primary"
                    type="button"
                    data-file-label-print
                    data-print-href="{{ route('files.label', ['file' => $file, 'type' => 'front']) }}"
                >Print</button>
            </div>
        </div>
    </div>
@endcan

@can('files.delete')
    <div class="modal-overlay hidden" data-modal="delete-file-{{ $file->id }}" aria-hidden="true">
        <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="delete-file-title-{{ $file->id }}">
            <div class="modal-header">
                <div>
                    <p class="eyebrow text-amber-700">{{ $file->file_identifier }}</p>
                    <h2 id="delete-file-title-{{ $file->id }}" class="mt-1 text-2xl font-semibold">Delete file</h2>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
            </div>
            <p class="mt-5 text-sm text-stone-600">
                Permanently delete <strong>{{ $file->reference_number }}</strong>
                ({{ $file->purchaser }})? Related links and movement history for this file will also be removed. This cannot be undone.
            </p>
            <form class="mt-6 flex justify-end gap-3" method="POST" action="{{ route('files.destroy', $file) }}">
                @csrf
                @method('DELETE')
                <button class="btn-secondary" type="button" data-modal-close>Cancel</button>
                <button class="btn-danger" type="submit">Delete file</button>
            </form>
        </div>
    </div>
@endcan

@if($file->status === \App\Models\LegalFile::STATUS_REGISTERED)
    @can('completions.request')
        <div class="modal-overlay hidden" data-modal="request-completion-{{ $file->id }}" aria-hidden="true">
            <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="request-completion-title-{{ $file->id }}">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow text-amber-700">{{ $file->reference_number }}</p>
                        <h2 id="request-completion-title-{{ $file->id }}" class="mt-1 text-2xl font-semibold">Request completion</h2>
                    </div>
                    <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
                </div>
                <p class="mt-5 text-sm text-stone-600">
                    Submit this file for boss review. Bring the physical file for checking. After approval it will move to Completed and can be stored.
                </p>
                <form class="mt-5 grid gap-4" method="POST" action="{{ route('completions.request', $file) }}">
                    @csrf
                    <label class="block">
                        <span class="form-label">Notes (optional)</span>
                        <textarea class="form-input mt-1.5 min-h-24" name="notes" placeholder="Any notes for the reviewer"></textarea>
                    </label>
                    <div class="flex justify-end gap-3">
                        <button class="btn-secondary" type="button" data-modal-close>Cancel</button>
                        <button class="btn-primary" type="submit">Submit for review</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endif

@if($file->status === \App\Models\LegalFile::STATUS_PENDING)
    @can('completions.review')
        <div class="modal-overlay hidden" data-modal="reject-completion-{{ $file->id }}" aria-hidden="true">
            <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="reject-completion-title-{{ $file->id }}">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow text-amber-700">{{ $file->reference_number }}</p>
                        <h2 id="reject-completion-title-{{ $file->id }}" class="mt-1 text-2xl font-semibold">Reject completion</h2>
                    </div>
                    <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
                </div>
                <form class="mt-5 grid gap-4" method="POST" action="{{ route('completions.reject', $file) }}">
                    @csrf
                    <label class="block">
                        <span class="form-label">Reason</span>
                        <textarea class="form-input mt-1.5 min-h-28" name="notes" required placeholder="What needs to be fixed before completion?"></textarea>
                    </label>
                    <div class="flex justify-end gap-3">
                        <button class="btn-secondary" type="button" data-modal-close>Cancel</button>
                        <button class="btn-danger" type="submit">Reject &amp; return to registered</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endif
@endsection
