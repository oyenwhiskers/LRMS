@extends('layouts.entry')

@section('title', 'Token AI')

@section('content')
@php
    $fmt = fn (int $n): string => number_format($n);
    $money = fn (float $n): string => \App\Support\AiTokenCost::formatMyr($n);
@endphp
<section class="entry-ai">
    <header class="entry-ai__head">
        <p class="entry-ai__eyebrow">Gemini</p>
        <h1 class="entry-ai__title">Token AI</h1>
    </header>

    <form class="entry-ai__filters" method="GET" action="{{ route('entry.ai-usage.index') }}">
        <div class="entry-ai__chips" role="group" aria-label="Tempoh">
            <a
                class="entry-ai__chip {{ $period === 'today' ? 'is-active' : '' }}"
                href="{{ route('entry.ai-usage.index', ['period' => 'today']) }}"
            >Hari ini</a>
            <a
                class="entry-ai__chip {{ $period === 'month' ? 'is-active' : '' }}"
                href="{{ route('entry.ai-usage.index', ['period' => 'month', 'month' => $monthValue]) }}"
            >Bulan</a>
            <a
                class="entry-ai__chip {{ $period === 'date' ? 'is-active' : '' }}"
                href="{{ route('entry.ai-usage.index', ['period' => 'date', 'date' => $dateValue]) }}"
            >Tarikh</a>
        </div>

        @if ($period === 'month')
            <label class="entry-ai__pick">
                <span>Pilih bulan</span>
                <input type="hidden" name="period" value="month">
                <input type="month" name="month" value="{{ $monthValue }}" onchange="this.form.submit()">
            </label>
        @endif

        @if ($period === 'date')
            <label class="entry-ai__pick">
                <span>Pilih tarikh</span>
                <input type="hidden" name="period" value="date">
                <input type="date" name="date" value="{{ $dateValue }}" onchange="this.form.submit()">
            </label>
        @endif
    </form>

    <p class="entry-ai__period">{{ $periodLabel }}</p>

    <article class="entry-ai__hero">
        <p class="entry-ai__hero-label">Anggaran kos</p>
        <p class="entry-ai__hero-cost">{{ $money($costMyr) }}</p>
        <p class="entry-ai__hero-hint">RM · gemini-3.1-flash-lite</p>

        <div class="entry-ai__metrics">
            <div class="entry-ai__metric">
                <p class="entry-ai__metric-label">Input</p>
                <p class="entry-ai__metric-value">{{ $fmt($inputTokens) }}</p>
                <p class="entry-ai__metric-unit">token</p>
            </div>
            <div class="entry-ai__metric">
                <p class="entry-ai__metric-label">Output</p>
                <p class="entry-ai__metric-value">{{ $fmt($outputTokens) }}</p>
                <p class="entry-ai__metric-unit">token</p>
            </div>
        </div>
    </article>

    <section class="entry-ai__ops" aria-labelledby="entry-ai-ops-title">
        <div class="entry-ai__ops-head">
            <h2 id="entry-ai-ops-title">Mengikut operator</h2>
        </div>

        @forelse ($operators as $op)
            <article class="entry-ai__op">
                <div class="entry-ai__op-top">
                    <div class="entry-ai__op-who">
                        <p class="entry-ai__op-name">{{ $op['name'] }}</p>
                        @if ($op['email'])
                            <p class="entry-ai__op-email">{{ $op['email'] }}</p>
                        @endif
                    </div>
                    <p class="entry-ai__op-cost">{{ $money($op['cost_myr']) }}</p>
                </div>
                <div class="entry-ai__op-tokens">
                    <span>In {{ $fmt($op['input_tokens']) }}</span>
                    <span>Out {{ $fmt($op['output_tokens']) }}</span>
                </div>
            </article>
        @empty
            <div class="entry-ai__empty">
                <p>Tiada penggunaan AI untuk tempoh ini.</p>
            </div>
        @endforelse
    </section>
</section>
@endsection
