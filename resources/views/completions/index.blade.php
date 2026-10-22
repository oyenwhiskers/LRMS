@extends('layouts.app')

@section('title', 'Completion')
@section('list_layout')
@endsection

@section('content')
<div class="list-page">
    <div class="list-page__header hidden lg:block">
        <p class="eyebrow text-amber-700">Approvals</p>
        <h1 class="mt-1 text-3xl font-semibold">Completion</h1>
        <p class="mt-2 text-sm text-stone-500">
            Review physical files submitted for completion. Approve to send them to storage, or reject to return them to registered.
        </p>
    </div>

    <form class="list-page__filters grid gap-3 border border-stone-200 bg-white p-4" method="GET" data-list-filter-form>
        <input
            class="form-input"
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search file, purchaser, PIC, or category"
            data-list-filter-search
        >
    </form>

    {{-- Desktop table --}}
    <div @class([
        'list-page__table hidden',
        'lg:block' => $files->isNotEmpty(),
        'list-page__table--empty lg:flex' => $files->isEmpty(),
    ])>
        <table class="data-table">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Purchaser</th>
                    <th>PIC</th>
                    <th>Category</th>
                    <th>Submitted</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            @if($files->isNotEmpty())
                <tbody>
                    @foreach($files as $file)
                        <tr>
                            <td>
                                <a class="accent-link font-semibold hover:underline" href="{{ route('files.show', $file) }}">{{ $file->reference_number }}</a>
                                <small>{{ $file->file_identifier }}</small>
                            </td>
                            <td>{{ $file->purchaser }}</td>
                            <td>{{ $file->personInCharge?->full_name ?? '—' }}</td>
                            <td>{{ $file->caseCategory?->code ?? '—' }}</td>
                            <td class="whitespace-nowrap">{{ $file->updated_at?->format('d M Y H:i') }}</td>
                            <td>
                                <div class="flex flex-wrap items-center justify-center gap-2">
                                    <button
                                        class="btn-danger"
                                        type="button"
                                        data-reject-completion
                                        data-reject-url="{{ route('completions.reject', $file) }}"
                                        data-reject-ref="{{ $file->reference_number }}"
                                    >Reject</button>
                                    <form method="POST" action="{{ route('completions.approve', $file) }}">
                                        @csrf
                                        <button class="btn-success" type="submit">Approve</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            @endif
        </table>
        @if($files->isEmpty())
            @include('layouts.partials.table-empty', [
                'icon' => 'approvals',
                'title' => 'No files awaiting completion',
                'message' => 'When staff request completion, files will appear here for review.',
            ])
        @endif
    </div>

    {{-- Mobile action cards --}}
    <div @class(['list-page__mobile lg:hidden', 'list-page__mobile--empty' => $files->isEmpty()])>
        @if($files->isNotEmpty())
            <div
                class="mobile-list"
                data-infinite-list
                data-infinite-next="{{ $files->hasMorePages() ? $files->currentPage() + 1 : '' }}"
                data-infinite-window="50"
            >
                @include('completions.partials.mobile-completion-cards', ['files' => $files])
            </div>
            <div class="infinite-scroll-status lg:hidden" data-infinite-status hidden></div>
            <div class="infinite-scroll-sentinel lg:hidden" data-infinite-sentinel @class(['hidden' => ! $files->hasMorePages()]) aria-hidden="true"></div>
        @else
            @include('layouts.partials.table-empty', [
                'icon' => 'approvals',
                'title' => 'No files awaiting completion',
                'message' => 'When staff request completion, files will appear here for review.',
            ])
        @endif
    </div>

    <div class="list-page__footer hidden lg:block">{{ $files->links() }}</div>
</div>

<div class="modal-overlay hidden" data-modal="reject-completion" aria-hidden="true">
    <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="reject-completion-title">
        <div class="modal-header">
            <div>
                <p class="eyebrow text-amber-700" data-reject-completion-ref></p>
                <h2 id="reject-completion-title" class="mt-1 text-2xl font-semibold">Reject completion</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
        </div>
        <form class="mt-5 grid gap-4" method="POST" data-reject-completion-form>
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
@endsection
