@foreach($registrations as $registration)
    @php
        $isPending = $registration->status === App\Models\User::STATUS_PENDING;
        $requestedPosition = $registration->requestedPosition?->name ?? 'Unavailable';
    @endphp
    <article class="approval-card" data-infinite-id="registration-{{ $registration->id }}">
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
                <div class="approval-card__actions">
                    <div class="approval-card__action-panel">
                        <div>
                            <p class="approval-card__label">Position assignment</p>
                            <p class="approval-card__value mt-1">{{ $requestedPosition }}</p>
                        </div>
                        <div class="approval-card__button-row">
                            @can('registrations.approve')
                                <button
                                    class="btn-primary approval-card__button"
                                    type="button"
                                    data-modal-open="approve-registration-{{ $registration->id }}"
                                >Approve</button>
                            @endcan
                            @can('registrations.reject')
                                <button
                                    class="btn-secondary approval-card__button"
                                    type="button"
                                    data-modal-open="reject-registration-{{ $registration->id }}"
                                >Reject</button>
                            @endcan
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </article>
@endforeach
