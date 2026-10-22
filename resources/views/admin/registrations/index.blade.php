@extends('layouts.app')

@section('title', 'Registration approvals')
@section('list_layout')
@endsection

@section('content')
<div class="list-page">
    <div class="list-page__header mb-0 hidden flex-col gap-3 lg:mb-0 lg:flex lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow text-amber-700">Administration</p>
            <h1 class="mt-1 text-3xl font-semibold">Registration approvals</h1>
            <p class="mt-2 max-w-2xl text-sm text-stone-600">Review and process account requests from the staff registry.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 lg:justify-end lg:gap-3">
            <span class="approval-summary-chip">{{ number_format($pendingCount) }} pending</span>
            <span class="text-xs uppercase tracking-[0.14em] text-stone-500">{{ number_format($registrations->total()) }} shown</span>
        </div>
    </div>

    <form class="list-page__filters approval-toolbar" method="GET" data-list-filter-form>
        <div class="approval-toolbar__search">
            <input
                class="form-input approval-toolbar__control"
                type="search"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search staff name or staff number"
                data-list-filter-search
            >
        </div>
        <div class="approval-toolbar__filters">
            <select class="form-input approval-toolbar__control" name="status" data-list-filter-select>
                <option value="">All statuses</option>
                @foreach([App\Models\User::STATUS_PENDING, App\Models\User::STATUS_APPROVED, App\Models\User::STATUS_REJECTED] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <select class="form-input approval-toolbar__control" name="sort" data-list-filter-select>
                <option value="latest" @selected(request('sort', 'latest') === 'latest')>Newest first</option>
                <option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option>
            </select>
        </div>
        <button class="btn-primary approval-toolbar__submit" type="submit">Apply</button>
    </form>

    <div @class(['list-page__mobile', 'list-page__mobile--empty' => $registrations->isEmpty()])>
        @if($registrations->isNotEmpty())
            <div
                class="space-y-4"
                data-infinite-list
                data-infinite-next="{{ $registrations->hasMorePages() ? $registrations->currentPage() + 1 : '' }}"
                data-infinite-window="30"
            >
                @include('admin.registrations.partials.registration-cards', ['registrations' => $registrations])
            </div>
            <div class="infinite-scroll-status lg:hidden" data-infinite-status hidden></div>
            <div class="infinite-scroll-sentinel" data-infinite-sentinel @class(['hidden' => ! $registrations->hasMorePages()]) aria-hidden="true"></div>
        @else
            <div class="border border-dashed border-stone-300 bg-white p-10 text-center text-sm text-stone-500">No registration requests match the current queue filters.</div>
        @endif
    </div>

    <div class="list-page__footer hidden lg:block">{{ $registrations->links() }}</div>
</div>

<div data-infinite-modals>
    @include('admin.registrations.partials.registration-modals', [
        'registrations' => $registrations,
        'positions' => $positions,
    ])
</div>
@endsection
