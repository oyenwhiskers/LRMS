@php
    /** @var string $modalId */
    /** @var string $title */
    /** @var string $eyebrow */
    /** @var string $lede */
    /** @var int $total */
    /** @var string $mode files|operators */
    /** @var \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $items */
    $mode = $mode ?? 'files';
    $filterQuery = $filterQuery ?? [];
@endphp
<div class="modal-overlay hidden" data-modal="{{ $modalId }}" aria-hidden="true">
    <div class="modal-panel entry-ops-metric-modal max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="{{ $modalId }}-title">
        <div class="modal-header">
            <div class="min-w-0">
                <p class="eyebrow text-amber-700">{{ $eyebrow }}</p>
                <h2 id="{{ $modalId }}-title" class="mt-1 text-xl font-semibold">{{ $title }}</h2>
                <p class="entry-ops-metric-modal__lede">{{ $lede }} · {{ $total }} rekod</p>
            </div>
            <button class="modal-close shrink-0" type="button" data-modal-close aria-label="Tutup">Tutup</button>
        </div>

        <div class="entry-ops-metric-modal__body">
            @if ($mode === 'operators')
                @forelse ($items as $op)
                    <a
                        class="entry-ops-metric-modal__row"
                        href="{{ route('entry.operators.index', array_filter($filterQuery + ['user_id' => $op['user_id']])) }}"
                    >
                        <div class="entry-ops-metric-modal__main">
                            <p class="entry-ops-metric-modal__title">{{ $op['name'] }}</p>
                            <p class="entry-ops-metric-modal__meta">{{ $op['email'] ?: '—' }}</p>
                        </div>
                        <span class="entry-ops__badge">{{ $op['count'] }} fail</span>
                    </a>
                @empty
                    <div class="entry-empty">
                        <p class="entry-empty__title">Tiada operator</p>
                        <p class="entry-empty__text">Tiada pendaftaran untuk tapisan semasa.</p>
                    </div>
                @endforelse
            @else
                @forelse ($items as $scan)
                    @continue(! $scan->legalFile)
                    <button
                        type="button"
                        class="entry-ops-metric-modal__row"
                        data-modal-open="operator-review-{{ $scan->id }}"
                    >
                        <div class="entry-ops-metric-modal__main">
                            <p class="entry-ops-metric-modal__title">{{ $scan->legalFile->reference_number }}</p>
                            <p class="entry-ops-metric-modal__meta">
                                {{ $scan->user?->name ?: '—' }}
                                · {{ $scan->legalFile->purchaser ?: '—' }}
                            </p>
                            <p class="entry-ops-metric-modal__shelf">
                                {{ \App\Support\LocalDate::format($scan->created_at, 'd/m/Y H:i') }}
                                @if ($scan->shelf)
                                    · {{ $scan->shelf->full_location }}
                                @elseif ($scan->status === App\Models\EntryScan::STATUS_SAVED)
                                    · Belum di rak
                                @endif
                            </p>
                        </div>
                        <span class="entry-ops__badge entry-ops__badge--review-{{ $scan->review_status ?: App\Models\EntryScan::REVIEW_PENDING }}">
                            {{ $scan->review_status === App\Models\EntryScan::REVIEW_PENDING
                                ? $scan->statusLabel()
                                : $scan->reviewStatusLabel() }}
                        </span>
                    </button>
                @empty
                    <div class="entry-empty">
                        <p class="entry-empty__title">Tiada fail</p>
                        <p class="entry-empty__text">Tiada rekod untuk metrik ini.</p>
                    </div>
                @endforelse
            @endif

            @if ($mode === 'files' && $total > $items->count())
                <p class="entry-ops-metric-modal__cap">
                    Menunjukkan {{ $items->count() }} daripada {{ $total }}. Guna carian atau tapis untuk cari rekod lain.
                </p>
            @endif
        </div>
    </div>
</div>
