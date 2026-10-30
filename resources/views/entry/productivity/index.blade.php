@extends('layouts.entry')

@section('title', 'Produktiviti')

@section('content')
@php
    $fmt = fn (int|float $n): string => number_format($n, is_float($n) && floor($n) !== $n ? 1 : 0);
    $fmtRate = function (?float $n, int $decimals = 1): string {
        if ($n === null) {
            return '—';
        }

        return number_format($n, abs($n - round($n)) < 0.0001 ? 0 : $decimals);
    };
@endphp
<div class="entry-prod">
    <header class="entry-prod__head">
        <div>
            <p class="entry-prod__eyebrow">Pemantauan</p>
            <h1 class="entry-prod__title">Produktiviti</h1>
        </div>
        <p class="entry-prod__period">{{ $periodLabel }}</p>
    </header>

    <form class="entry-prod__filters" method="GET" action="{{ route('entry.productivity.index') }}">
        <div class="entry-prod__chips" role="group" aria-label="Tempoh">
            <a
                class="entry-prod__chip {{ $period === 'today' ? 'is-active' : '' }}"
                href="{{ route('entry.productivity.index', ['period' => 'today', 'date' => $dateValue]) }}"
            >Hari</a>
            <a
                class="entry-prod__chip {{ $period === 'month' ? 'is-active' : '' }}"
                href="{{ route('entry.productivity.index', ['period' => 'month', 'month' => $monthValue]) }}"
            >Bulan</a>
            <a
                class="entry-prod__chip {{ $period === 'year' ? 'is-active' : '' }}"
                href="{{ route('entry.productivity.index', ['period' => 'year', 'year' => $yearValue]) }}"
            >Tahun</a>
        </div>

        @if ($period === 'today')
            <div
                class="entry-prod__pick entry-prod__day-picker"
                data-prod-day-picker
                data-work-dates='@json($workDates)'
                data-selected="{{ $dateValue }}"
            >
                <span>Pilih tarikh</span>
                <input type="hidden" name="period" value="today">
                <input type="hidden" name="date" value="{{ $dateValue }}" data-prod-day-value>
                <button
                    type="button"
                    class="entry-prod__day-toggle"
                    data-prod-day-toggle
                    aria-expanded="false"
                    aria-controls="entry-prod-day-panel"
                >
                    <span data-prod-day-summary>{{ $periodLabel }}</span>
                    <span class="entry-prod__day-toggle-icon" aria-hidden="true">▾</span>
                </button>
                <div
                    class="entry-prod__cal hidden"
                    id="entry-prod-day-panel"
                    data-prod-day-panel
                    role="dialog"
                    aria-label="Kalendar hari kerja"
                >
                    <div class="entry-prod__cal-nav">
                        <button type="button" class="entry-prod__cal-nav-btn" data-prod-day-prev aria-label="Bulan sebelumnya">‹</button>
                        <p class="entry-prod__cal-month" data-prod-day-label></p>
                        <button type="button" class="entry-prod__cal-nav-btn" data-prod-day-next aria-label="Bulan seterusnya">›</button>
                    </div>
                    <div class="entry-prod__cal-weekdays" aria-hidden="true">
                        <span>Is</span><span>Se</span><span>Ra</span><span>Kh</span><span>Ju</span><span>Sa</span><span>Ah</span>
                    </div>
                    <div class="entry-prod__cal-grid" data-prod-day-grid></div>
                    <p class="entry-prod__cal-hint">Hari tanpa daftar dikaburkan</p>
                </div>
            </div>
        @endif

        @if ($period === 'month')
            <label class="entry-prod__pick">
                <span>Pilih bulan</span>
                <input type="hidden" name="period" value="month">
                <input type="month" name="month" value="{{ $monthValue }}" onchange="this.form.submit()">
            </label>
        @endif

        @if ($period === 'year')
            <label class="entry-prod__pick">
                <span>Pilih tahun</span>
                <input type="hidden" name="period" value="year">
                <input
                    type="number"
                    name="year"
                    min="2000"
                    max="2100"
                    step="1"
                    value="{{ $yearValue }}"
                    onchange="this.form.submit()"
                >
            </label>
        @endif
    </form>

    <section class="entry-prod__section" aria-labelledby="entry-prod-reg-title">
        <div class="entry-prod__section-head">
            <h2 id="entry-prod-reg-title">Jumlah daftar</h2>
            <p>Isipadu pendaftaran fail</p>
        </div>

        <div class="entry-prod__stats entry-prod__stats--register">
            <article class="entry-prod__stat entry-prod__stat--focus">
                <p class="entry-prod__stat-label">Hari ini</p>
                <p class="entry-prod__stat-value">{{ $fmt($registeredToday) }}</p>
                <p class="entry-prod__stat-hint">Fail disimpan / di rak</p>
            </article>
            <article class="entry-prod__stat">
                <p class="entry-prod__stat-label">Tempoh ini</p>
                <p class="entry-prod__stat-value">{{ $fmt($registeredInPeriod) }}</p>
                <p class="entry-prod__stat-hint">{{ $periodLabel }}</p>
            </article>
            <article class="entry-prod__stat">
                <p class="entry-prod__stat-label">Purata / hari</p>
                <p class="entry-prod__stat-value">{{ $fmt($avgPerDay) }}</p>
                <p class="entry-prod__stat-hint">Bulan ini ÷ hari dalam bulan · {{ $fmt($monthCount) }} fail</p>
            </article>
            <article class="entry-prod__stat">
                <p class="entry-prod__stat-label">Purata / bulan</p>
                <p class="entry-prod__stat-value">{{ $fmt($avgPerMonth) }}</p>
                <p class="entry-prod__stat-hint">Tahun ini ÷ 12 · {{ $fmt($yearCount) }} fail</p>
            </article>
        </div>
    </section>

    <section class="entry-prod__section" aria-labelledby="entry-prod-flow-title">
        <div class="entry-prod__section-head">
            <h2 id="entry-prod-flow-title">Status aliran</h2>
            <p>Kedudukan fail dalam proses · {{ $periodLabel }}</p>
        </div>

        <div class="entry-prod__stats entry-prod__stats--flow">
            <article class="entry-prod__stat">
                <p class="entry-prod__stat-label">Belum di rak</p>
                <p class="entry-prod__stat-value">{{ $fmt($pendingShelf) }}</p>
            </article>
            <article class="entry-prod__stat">
                <p class="entry-prod__stat-label">Di rak</p>
                <p class="entry-prod__stat-value">{{ $fmt($onShelf) }}</p>
            </article>
            <article class="entry-prod__stat entry-prod__stat--warn">
                <p class="entry-prod__stat-label">Perlu betul</p>
                <p class="entry-prod__stat-value">{{ $fmt($needsCorrection) }}</p>
            </article>
            <article class="entry-prod__stat entry-prod__stat--ok">
                <p class="entry-prod__stat-label">Disahkan</p>
                <p class="entry-prod__stat-value">{{ $fmt($verified) }}</p>
            </article>
            <article class="entry-prod__stat">
                <p class="entry-prod__stat-label">Belum semak</p>
                <p class="entry-prod__stat-value">{{ $fmt($pendingReview) }}</p>
            </article>
            <article class="entry-prod__stat">
                <p class="entry-prod__stat-label">Operator aktif</p>
                <p class="entry-prod__stat-value">{{ $fmt($operators->count()) }}</p>
            </article>
        </div>
    </section>

    <section class="entry-prod__section" aria-labelledby="entry-prod-ops-title">
        <div class="entry-prod__section-head">
            <h2 id="entry-prod-ops-title">Operator</h2>
            <p>Jumlah fail dan kadar setiap operator · {{ $periodLabel }}</p>
        </div>

        @php
            $rateTip = match ($period) {
                'month' => 'Fail/hari: jumlah fail ÷ bilangan hari dalam bulan tersebut. Fail/minggu = fail/hari × 7.',
                'year' => 'Fail/bulan: jumlah fail dalam tahun ÷ 12.',
                default => 'Fail/min: jumlah fail ÷ masa dari fail pertama ke terakhir hari itu. Fail/jam = fail/min × 60.',
            };
        @endphp

        <div class="entry-prod__ops" data-period="{{ $period }}" role="list">
            <div class="entry-prod__ops-cols">
                <span>Operator</span>
                <span>Fail</span>
                @if ($period === 'today')
                    <span class="entry-prod__col-with-info">
                        Fail/min
                        <span class="entry-prod__info">
                            <button
                                type="button"
                                class="entry-prod__info-btn"
                                aria-describedby="entry-prod-rate-tip"
                                aria-label="Maklumat kadar"
                            >
                                <span aria-hidden="true">i</span>
                            </button>
                            <span class="entry-prod__info-pop" id="entry-prod-rate-tip" role="tooltip">
                                {{ $rateTip }}
                            </span>
                        </span>
                    </span>
                    <span>Fail/jam</span>
                @elseif ($period === 'month')
                    <span class="entry-prod__col-with-info">
                        Fail/hari
                        <span class="entry-prod__info">
                            <button
                                type="button"
                                class="entry-prod__info-btn"
                                aria-describedby="entry-prod-rate-tip"
                                aria-label="Maklumat kadar"
                            >
                                <span aria-hidden="true">i</span>
                            </button>
                            <span class="entry-prod__info-pop" id="entry-prod-rate-tip" role="tooltip">
                                {{ $rateTip }}
                            </span>
                        </span>
                    </span>
                    <span>Fail/minggu</span>
                @else
                    <span class="entry-prod__col-with-info">
                        Fail/bulan
                        <span class="entry-prod__info">
                            <button
                                type="button"
                                class="entry-prod__info-btn"
                                aria-describedby="entry-prod-rate-tip"
                                aria-label="Maklumat kadar"
                            >
                                <span aria-hidden="true">i</span>
                            </button>
                            <span class="entry-prod__info-pop" id="entry-prod-rate-tip" role="tooltip">
                                {{ $rateTip }}
                            </span>
                        </span>
                    </span>
                @endif
            </div>

            <div class="entry-prod__ops-mobile-info">
                <span class="entry-prod__col-with-info">
                    Kadar
                    <span class="entry-prod__info">
                        <button
                            type="button"
                            class="entry-prod__info-btn"
                            aria-describedby="entry-prod-rate-tip-mobile"
                            aria-label="Maklumat kadar"
                        >
                            <span aria-hidden="true">i</span>
                        </button>
                        <span class="entry-prod__info-pop" id="entry-prod-rate-tip-mobile" role="tooltip">
                            {{ $rateTip }}
                        </span>
                    </span>
                </span>
            </div>

            @forelse ($operators as $op)
                <article class="entry-prod__op" role="listitem">
                    <div class="entry-prod__op-who">
                        <p class="entry-prod__op-name">{{ $op['name'] }}</p>
                        @if ($op['email'])
                            <p class="entry-prod__op-email">{{ $op['email'] }}</p>
                        @endif
                    </div>
                    <div class="entry-prod__op-cell" data-label="Fail">
                        <p class="entry-prod__op-value">{{ $fmt($op['count']) }}</p>
                    </div>
                    @if ($period === 'today')
                        <div class="entry-prod__op-cell" data-label="Fail/min">
                            <p class="entry-prod__op-value">{{ $fmtRate($op['files_per_minute'] ?? null, 2) }}</p>
                        </div>
                        <div class="entry-prod__op-cell" data-label="Fail/jam">
                            <p class="entry-prod__op-value">{{ $fmtRate($op['files_per_hour'] ?? null) }}</p>
                        </div>
                    @elseif ($period === 'month')
                        <div class="entry-prod__op-cell" data-label="Fail/hari">
                            <p class="entry-prod__op-value">{{ $fmtRate($op['files_per_day'] ?? null) }}</p>
                        </div>
                        <div class="entry-prod__op-cell" data-label="Fail/minggu">
                            <p class="entry-prod__op-value">{{ $fmtRate($op['files_per_week'] ?? null) }}</p>
                        </div>
                    @else
                        <div class="entry-prod__op-cell" data-label="Fail/bulan">
                            <p class="entry-prod__op-value">{{ $fmtRate($op['files_per_month'] ?? null) }}</p>
                        </div>
                    @endif
                </article>
            @empty
                <div class="entry-prod__empty">
                    <p>Tiada pendaftaran untuk tempoh ini.</p>
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection
