@foreach($registrations as $registration)
    @php
        $isPending = $registration->status === App\Models\User::STATUS_PENDING;
        $requestedPosition = $registration->requestedPosition?->name ?? 'Unavailable';
        $isActiveForm = (int) old('registration_id') === $registration->id;
        $currentPositionId = old('position_id') ? (int) old('position_id') : $registration->requested_position_id;
        $showOverride = $isActiveForm && old('position_id') && (int) old('position_id') !== (int) $registration->requested_position_id;
        $openRejectModal = $isActiveForm && old('decision') === 'reject';
        $openApproveModal = $isActiveForm && ! $openRejectModal;
    @endphp
    @if($isPending)
        @can('registrations.approve')
            <div
                class="modal-overlay {{ $openApproveModal ? '' : 'hidden' }}"
                data-modal="approve-registration-{{ $registration->id }}"
                data-infinite-id="registration-{{ $registration->id }}"
                aria-hidden="{{ $openApproveModal ? 'false' : 'true' }}"
            >
                <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="approve-registration-title-{{ $registration->id }}">
                    <div class="modal-header">
                        <div>
                            <p class="eyebrow text-amber-700">{{ $registration->name }}</p>
                            <h2 id="approve-registration-title-{{ $registration->id }}" class="mt-1 text-2xl font-semibold">Approve registration</h2>
                        </div>
                        <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
                    </div>
                    <form class="mt-5 grid gap-4" method="POST" action="{{ route('admin.registrations.update', $registration) }}" data-approval-form>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="registration_id" value="{{ $registration->id }}">
                        <input type="hidden" name="position_id" value="{{ $currentPositionId }}" data-position-id data-default-position="{{ $registration->requested_position_id }}">

                        <div>
                            <p class="approval-card__label">Requested position</p>
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
                            <select class="form-input mt-1.5" id="position-{{ $registration->id }}" data-position-select>
                                @foreach($positions as $position)
                                    <option value="{{ $position->id }}" @selected($currentPositionId === $position->id)>{{ $position->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button class="btn-secondary" type="button" data-modal-close>Cancel</button>
                            <button class="btn-primary" name="decision" value="approve" type="submit">Confirm approve</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan

        @can('registrations.reject')
            <div
                class="modal-overlay {{ $openRejectModal ? '' : 'hidden' }}"
                data-modal="reject-registration-{{ $registration->id }}"
                data-infinite-id="registration-{{ $registration->id }}"
                aria-hidden="{{ $openRejectModal ? 'false' : 'true' }}"
            >
                <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="reject-registration-title-{{ $registration->id }}">
                    <div class="modal-header">
                        <div>
                            <p class="eyebrow text-amber-700">{{ $registration->name }}</p>
                            <h2 id="reject-registration-title-{{ $registration->id }}" class="mt-1 text-2xl font-semibold">Reject registration</h2>
                        </div>
                        <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
                    </div>
                    <form class="mt-5 grid gap-4" method="POST" action="{{ route('admin.registrations.update', $registration) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="registration_id" value="{{ $registration->id }}">
                        <input type="hidden" name="position_id" value="{{ $registration->requested_position_id }}">
                        <label class="block">
                            <span class="form-label">Reason</span>
                            <textarea
                                class="form-input mt-1.5 min-h-28"
                                name="rejection_reason"
                                required
                                placeholder="Why is this registration being rejected?"
                            >{{ $openRejectModal ? old('rejection_reason') : '' }}</textarea>
                        </label>
                        <div class="flex justify-end gap-3">
                            <button class="btn-secondary" type="button" data-modal-close>Cancel</button>
                            <button class="btn-danger" name="decision" value="reject" type="submit">Confirm reject</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endif
@endforeach
