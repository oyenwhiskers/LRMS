@extends('layouts.app')

@section('title', 'Registration approvals')

@section('content')
<div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div>
        <p class="eyebrow text-amber-700">Administration</p>
        <h1 class="mt-1 text-3xl font-semibold">Registration approvals</h1>
        <p class="mt-2 max-w-2xl text-sm text-stone-600">Review and process account requests from the staff registry.</p>
    </div>
    <div class="flex flex-wrap items-center gap-3 lg:justify-end">
        <span class="approval-summary-chip">{{ number_format($pendingCount) }} pending requests</span>
        <span class="text-xs uppercase tracking-[0.14em] text-stone-500">{{ number_format($registrations->total()) }} shown</span>
    </div>
</div>

<form class="approval-toolbar mb-6" method="GET">
    <div class="approval-toolbar__search">
        <input class="form-input approval-toolbar__control" type="search" name="search" value="{{ request('search') }}" placeholder="Search staff name or staff number">
    </div>
    <select class="form-input approval-toolbar__control" name="status">
        <option value="">All statuses</option>
        @foreach([App\Models\User::STATUS_PENDING, App\Models\User::STATUS_APPROVED, App\Models\User::STATUS_REJECTED] as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
    <select class="form-input approval-toolbar__control" name="sort">
        <option value="latest" @selected(request('sort', 'latest') === 'latest')>Newest first</option>
        <option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option>
    </select>
    <button class="btn-primary approval-toolbar__submit" type="submit">Apply</button>
</form>

<div class="space-y-4">
    @forelse($registrations as $registration)
        @php
            $isPending = $registration->status === App\Models\User::STATUS_PENDING;
            $requestedPosition = $registration->requestedPosition?->name ?? 'Unavailable';
            $isActiveForm = (int) old('registration_id') === $registration->id;
            $currentPositionId = old('position_id') ? (int) old('position_id') : $registration->requested_position_id;
            $showOverride = $isActiveForm && old('position_id') && (int) old('position_id') !== (int) $registration->requested_position_id;
            $showReject = $isActiveForm && old('decision') === 'reject';
        @endphp
        <article class="approval-card">
            <div class="approval-card__layout {{ $isPending ? '' : 'approval-card__layout--history' }}">
                <div class="approval-card__content {{ $isPending ? '' : 'approval-card__content--history' }}">
                    <div class="approval-card__header">
                        <div class="min-w-0">
                            <h2 class="approval-card__name">{{ $registration->name }}</h2>
                            <p class="approval-card__meta">{{ $requestedPosition }}</p>
                        </div>
                        <span class="approval-card__status status-badge status-{{ $registration->status }}">{{ ucfirst($registration->status) }}</span>
                    </div>

                    <dl class="approval-card__summary {{ $isPending ? '' : 'approval-card__summary--history' }}">
                        <div class="approval-card__summary-item">
                            <dt class="approval-card__label">Staff number</dt>
                            <dd class="approval-card__value">{{ $registration->staff?->staff_number ?? 'Unavailable' }}</dd>
                        </div>
                        <div class="approval-card__summary-item">
                            <dt class="approval-card__label">Email</dt>
                            <dd class="approval-card__value approval-card__value--break">{{ $registration->staff?->email ?? 'No email on record' }}</dd>
                        </div>
                        <div class="approval-card__summary-item">
                            <dt class="approval-card__label">Submitted</dt>
                            <dd class="approval-card__value">{{ $registration->created_at->format('d M Y, H:i') }}</dd>
                        </div>
                    </dl>

                    @if($registration->status === App\Models\User::STATUS_REJECTED)
                        <div class="approval-card__note">
                            <p class="approval-card__note-label">Rejection reason</p>
                            <p class="approval-card__note-copy">{{ $registration->rejection_reason }}</p>
                        </div>
                    @elseif($registration->reviewed_at)
                        <div class="approval-card__note approval-card__note--neutral">
                            <p class="approval-card__note-label">Approval note</p>
                            <p class="approval-card__note-copy">Reviewed {{ $registration->reviewed_at->diffForHumans() }} by {{ $registration->reviewer?->name ?? 'an administrator' }}.</p>
                        </div>
                    @endif
                </div>

                @if($isPending)
                    <form method="POST" action="{{ route('admin.registrations.update', $registration) }}" class="approval-card__actions" data-approval-form>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="registration_id" value="{{ $registration->id }}">
                        <input type="hidden" name="position_id" value="{{ $currentPositionId }}" data-position-id data-default-position="{{ $registration->requested_position_id }}">

                        <div class="approval-card__action-panel">
                            <div>
                                <div class="space-y-3">
                                    <div>
                                        <p class="approval-card__label">Position assignment</p>
                                        <p class="approval-card__value mt-1">{{ $requestedPosition }}</p>
                                    </div>

                                    <label class="approval-card__toggle">
                                        <input
                                            class="rounded border-stone-300 text-amber-700 focus:ring-amber-600"
                                            type="checkbox"
                                            value="1"
                                            data-override-toggle
                                            @checked($showOverride)
                                        >
                                        <span>Override position</span>
                                    </label>

                                    <div class="{{ $showOverride ? '' : 'hidden' }}" data-override-panel>
                                        <label class="form-label" for="position-{{ $registration->id }}">Confirmed position</label>
                                        <select class="form-input" id="position-{{ $registration->id }}" data-position-select>
                                            @foreach($positions as $position)
                                                <option value="{{ $position->id }}" @selected($currentPositionId === $position->id)>{{ $position->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="approval-card__button-row">
                                @can('registrations.approve')
                                    <button class="btn-primary approval-card__button" name="decision" value="approve" type="submit">Approve</button>
                                @endcan
                                @can('registrations.reject')
                                    <button class="btn-secondary approval-card__button" type="button" data-reject-toggle aria-expanded="{{ $showReject ? 'true' : 'false' }}">Reject</button>
                                @endcan
                            </div>

                            @can('registrations.reject')
                                <div class="approval-card__reject {{ $showReject ? '' : 'hidden' }}" data-reject-panel>
                                    <label class="form-label" for="reason-{{ $registration->id }}">Rejection reason</label>
                                    <textarea class="form-input min-h-24" id="reason-{{ $registration->id }}" name="rejection_reason" placeholder="Required when rejecting">{{ old('rejection_reason') }}</textarea>
                                    <div class="approval-card__button-row mt-3">
                                        <button class="btn-danger approval-card__button" name="decision" value="reject" type="submit">Confirm reject</button>
                                        <button class="btn-secondary approval-card__button" type="button" data-reject-cancel>Cancel</button>
                                    </div>
                                </div>
                            @endcan
                        </div>
                    </form>
                @endif
            </div>
        </article>
    @empty
        <div class="border border-dashed border-stone-300 bg-white p-10 text-center text-sm text-stone-500">No registration requests match the current queue filters.</div>
    @endforelse
</div>

<div class="mt-6">{{ $registrations->links() }}</div>
@endsection
