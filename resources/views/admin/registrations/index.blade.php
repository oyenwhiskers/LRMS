@extends('layouts.app')

@section('title', 'Registration approvals')

@section('content')
<div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="eyebrow text-amber-700">Administration</p>
        <h1 class="mt-2 text-3xl font-semibold">Registration approvals</h1>
        <p class="mt-2 text-sm text-stone-600">Verify staff requests, confirm the authoritative position, and record a decision.</p>
    </div>
    <span class="text-xs uppercase tracking-[0.14em] text-stone-500">{{ $registrations->total() }} requests</span>
</div>

<div class="space-y-4">
    @forelse($registrations as $registration)
        <article class="border border-stone-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-lg font-semibold">{{ $registration->name }}</h2>
                        <span class="status-badge status-{{ $registration->status }}">{{ ucfirst($registration->status) }}</span>
                    </div>
                    <dl class="mt-4 grid gap-x-8 gap-y-3 text-sm sm:grid-cols-3">
                        <div><dt class="form-help">Staff number</dt><dd class="font-medium">{{ $registration->staff?->staff_number }}</dd></div>
                        <div><dt class="form-help">Requested position</dt><dd class="font-medium">{{ $registration->requestedPosition?->name ?? 'Unavailable' }}</dd></div>
                        <div><dt class="form-help">Submitted</dt><dd class="font-medium">{{ $registration->created_at->format('d M Y, H:i') }}</dd></div>
                    </dl>
                    @if($registration->status === App\Models\User::STATUS_REJECTED)
                        <p class="mt-4 border-l-2 border-red-300 pl-3 text-sm text-stone-600">{{ $registration->rejection_reason }}</p>
                    @elseif($registration->reviewed_at)
                        <p class="mt-4 text-xs text-stone-500">Reviewed {{ $registration->reviewed_at->diffForHumans() }} by {{ $registration->reviewer?->name ?? 'an administrator' }}.</p>
                    @endif
                </div>

                @if($registration->status === App\Models\User::STATUS_PENDING)
                    <form method="POST" action="{{ route('admin.registrations.update', $registration) }}" class="w-full border-t border-stone-200 pt-4 lg:max-w-md lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                        @csrf
                        @method('PATCH')
                        <label class="form-label" for="position-{{ $registration->id }}">Confirmed position</label>
                        <select class="form-input" id="position-{{ $registration->id }}" name="position_id">
                            @foreach($positions as $position)
                                <option value="{{ $position->id }}" @selected($registration->requested_position_id === $position->id)>{{ $position->name }}</option>
                            @endforeach
                        </select>
                        <label class="form-label mt-3" for="reason-{{ $registration->id }}">Rejection reason</label>
                        <input class="form-input" id="reason-{{ $registration->id }}" name="rejection_reason" placeholder="Required when rejecting">
                        <div class="mt-4 flex flex-wrap gap-2">
                            @can('registrations.approve')
                                <button class="btn-primary" name="decision" value="approve" type="submit">Approve</button>
                            @endcan
                            @can('registrations.reject')
                                <button class="btn-danger" name="decision" value="reject" type="submit">Reject</button>
                            @endcan
                        </div>
                    </form>
                @endif
            </div>
        </article>
    @empty
        <div class="border border-dashed border-stone-300 bg-white p-12 text-center text-sm text-stone-500">No registration requests have been submitted.</div>
    @endforelse
</div>

<div class="mt-6">{{ $registrations->links() }}</div>
@endsection
