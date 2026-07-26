@extends('layouts.app')

@section('title', 'Registration approvals')

@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="eyebrow text-amber-700">Administration</p>
        <h1 class="mt-1 text-3xl font-semibold">Registration approvals</h1>
        <p class="mt-1 text-sm text-stone-600">Review and process account requests from the staff registry.</p>
    </div>
    <div class="flex items-center gap-3">
        <span class="status-badge status-pending">{{ number_format($pendingCount) }} pending requests</span>
        <span class="text-xs uppercase tracking-[0.14em] text-stone-500">{{ number_format($registrations->total()) }} shown</span>
    </div>
</div>

<form class="mb-6 grid gap-3 border border-stone-200 bg-white p-4 sm:grid-cols-[minmax(0,1fr)_180px_180px_auto]" method="GET">
    <input class="form-input" type="search" name="search" value="{{ request('search') }}" placeholder="Search staff name or staff number">
    <select class="form-input" name="status">
        <option value="">All statuses</option>
        @foreach([App\Models\User::STATUS_PENDING, App\Models\User::STATUS_APPROVED, App\Models\User::STATUS_REJECTED] as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
    <select class="form-input" name="sort">
        <option value="latest" @selected(request('sort', 'latest') === 'latest')>Newest first</option>
        <option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option>
    </select>
    <button class="btn-primary" type="submit">Apply</button>
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
        <article class="border border-stone-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h2 class="text-lg font-semibold leading-tight">{{ $registration->name }}</h2>
                        <span class="status-badge status-{{ $registration->status }}">{{ ucfirst($registration->status) }}</span>
                    </div>
                    <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2 xl:max-w-3xl xl:grid-cols-4">
                        <div>
                            <dt class="form-help">Staff number</dt>
                            <dd class="font-medium">{{ $registration->staff?->staff_number ?? 'Unavailable' }}</dd>
                        </div>
                        <div>
                            <dt class="form-help">Requested position</dt>
                            <dd class="font-medium">{{ $requestedPosition }}</dd>
                        </div>
                        <div>
                            <dt class="form-help">Submitted</dt>
                            <dd class="font-medium">{{ $registration->created_at->format('d M Y, H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="form-help">Staff record</dt>
                            <dd class="font-medium">{{ $registration->staff?->email ?? 'No email on record' }}</dd>
                        </div>
                    </dl>
                    @if($registration->status === App\Models\User::STATUS_REJECTED)
                        <p class="mt-3 border-l-2 border-red-300 pl-3 text-sm text-stone-600">{{ $registration->rejection_reason }}</p>
                    @elseif($registration->reviewed_at)
                        <p class="mt-3 text-xs text-stone-500">Reviewed {{ $registration->reviewed_at->diffForHumans() }} by {{ $registration->reviewer?->name ?? 'an administrator' }}.</p>
                    @endif
                </div>

                @if($isPending)
                    <form method="POST" action="{{ route('admin.registrations.update', $registration) }}" class="w-full border-t border-stone-200 pt-4 xl:max-w-md xl:border-l xl:border-t-0 xl:pl-5 xl:pt-0" data-approval-form>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="registration_id" value="{{ $registration->id }}">
                        <input type="hidden" name="position_id" value="{{ $currentPositionId }}" data-position-id data-default-position="{{ $registration->requested_position_id }}">

                        <div class="space-y-3">
                            <div>
                                <p class="form-help">Requested position</p>
                                <p class="mt-1 font-medium text-stone-900">{{ $requestedPosition }}</p>
                            </div>

                            <label class="inline-flex items-center gap-2 text-sm text-stone-600">
                                <input
                                    class="rounded border-stone-300 text-amber-700 focus:ring-amber-600"
                                    type="checkbox"
                                    value="1"
                                    data-override-toggle
                                    @checked($showOverride)
                                >
                                Override requested position
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

                        <div class="mt-4 flex flex-wrap gap-2">
                            @can('registrations.approve')
                                <button class="btn-primary min-w-28" name="decision" value="approve" type="submit">Approve</button>
                            @endcan
                            @can('registrations.reject')
                                <button class="btn-secondary min-w-28" type="button" data-reject-toggle aria-expanded="{{ $showReject ? 'true' : 'false' }}">Reject</button>
                            @endcan
                        </div>

                        @can('registrations.reject')
                            <div class="mt-3 {{ $showReject ? '' : 'hidden' }}" data-reject-panel>
                                <label class="form-label" for="reason-{{ $registration->id }}">Rejection reason</label>
                                <textarea class="form-input min-h-24" id="reason-{{ $registration->id }}" name="rejection_reason" placeholder="Required when rejecting">{{ old('rejection_reason') }}</textarea>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button class="btn-danger min-w-28" name="decision" value="reject" type="submit">Confirm reject</button>
                                    <button class="btn-secondary min-w-28" type="button" data-reject-cancel>Cancel</button>
                                </div>
                            </div>
                        @endcan
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
