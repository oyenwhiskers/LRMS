@extends('layouts.entry')

@section('title', 'Produktiviti')

@section('content')
@php
    $fmt = fn (int|float $n): string => number_format($n, is_float($n) && floor($n) !== $n ? 1 : 0);
    $speed = fn (?float $minutes): string => $minutes === null ? '—' : $fmt($minutes).' min';
    $rate = fn (?float $perHour): string => $perHour === null ? '—' : $fmt($perHour).'/j';
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
                href="{{ route('entry.productivity.index', ['period' => 'today']) }}"
            >Hari ini</a>
            <a
                class="entry-prod__chip {{ $period === 'month' ? 'is-active' : '' }}"
                href="{{ route('entry.productivity.index', ['period' => 'month', 'month' => $monthValue]) }}"
            >Bulan</a>
            <a
                class="entry-prod__chip {{ $period === 'date' ? 'is-active' : '' }}"
                href="{{ route('entry.productivity.index', ['period' => 'date', 'date' => $dateValue]) }}"
            >Tarikh</a>
        </div>

        @if ($period === 'month')
            <label class="entry-prod__pick">
                <span>Pilih bulan</span>
                <input type="hidden" name="period" value="month">
                <input type="month" name="month" value="{{ $monthValue }}" onchange="this.form.submit()">
            </label>
        @endif

        @if ($period === 'date')
            <label class="entry-prod__pick">
                <span>Pilih tarikh</span>
                <input type="hidden" name="period" value="date">
                <input type="date" name="date" value="{{ $dateValue }}" onchange="this.form.submit()">
            </label>
        @endif
    </form>

    <section class="entry-prod__section" aria-labelledby="entry-prod-general-title">
        <div class="entry-prod__section-head">
            <h2 id="entry-prod-general-title">Umum</h2>
            <p>Jumlah keseluruhan dan status aliran fail</p>
        </div>

        <div class="entry-prod__block">
            <h3 class="entry-prod__block-title">Jumlah daftar</h3>
            <div class="entry-prod__stats">
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
                    <p class="entry-prod__stat-hint">Bulan ini · {{ $fmt($monthCount) }} fail</p>
                </article>
                <article class="entry-prod__stat">
                    <p class="entry-prod__stat-label">Purata / bulan</p>
                    <p class="entry-prod__stat-value">{{ $fmt($avgPerMonth) }}</p>
                    <p class="entry-prod__stat-hint">Tahun ini · {{ $fmt($yearCount) }} fail</p>
                </article>
            </div>
        </div>

        <div class="entry-prod__block">
            <h3 class="entry-prod__block-title">Status aliran · {{ $periodLabel }}</h3>
            <div class="entry-prod__stats entry-prod__stats--flow">
                <article class="entry-prod__stat">
                    <p class="entry-prod__stat-label">Belum di rak</p>
                    <p class="entry-prod__stat-value">{{ $fmt($pendingShelf) }}</p>
                </article>
                <article class="entry-prod__stat">
                    <p class="entry-prod__stat-label">Di rak</p>
                    <p class="entry-prod__stat-value">{{ $fmt($onShelf) }}</p>
                </article>
                <article class="entry-prod__stat">
                    <p class="entry-prod__stat-label">Perlu betul</p>
                    <p class="entry-prod__stat-value">{{ $fmt($needsCorrection) }}</p>
                </article>
                <article class="entry-prod__stat">
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
        </div>
    </section>

    <section class="entry-prod__section" aria-labelledby="entry-prod-ops-title">
        <div class="entry-prod__section-head">
            <h2 id="entry-prod-ops-title">Operator</h2>
            <p>Jumlah fail dan kelajuan setiap operator · {{ $periodLabel }}</p>
        </div>

        <p class="entry-prod__note">Kelajuan = median jurang antara fail. Rehat lebih 2 jam tidak dikira.</p>

        <div class="entry-prod__ops" role="list">
            <div class="entry-prod__ops-cols" aria-hidden="true">
                <span>Operator</span>
                <span>Fail</span>
                <span>Kelajuan</span>
                <span>Kadar</span>
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
                    <div class="entry-prod__op-cell" data-label="Kelajuan">
                        <p class="entry-prod__op-value">{{ $speed($op['avg_minutes']) }}</p>
                        <p class="entry-prod__op-unit">/ fail</p>
                    </div>
                    <div class="entry-prod__op-cell" data-label="Kadar">
                        <p class="entry-prod__op-value">{{ $rate($op['files_per_hour']) }}</p>
                        <p class="entry-prod__op-unit">fail sejam</p>
                    </div>
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
