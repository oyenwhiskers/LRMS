@php
    $title = $title ?? '';
    $meta = $meta ?? null;
    $detail = $detail ?? null;
    $badge = $badge ?? null;
    $badgeTone = $badgeTone ?? 'neutral';
    $href = $href ?? null;
    $ariaLabel = $ariaLabel ?? $title;
@endphp

@if($href)
    <article
        class="mobile-list-card mobile-list-card--link"
        data-row-href="{{ $href }}"
        tabindex="0"
        role="link"
        aria-label="{{ $ariaLabel }}"
    >
@else
    <article class="mobile-list-card">
@endif
    <div class="mobile-list-card__header">
        <div class="mobile-list-card__title-wrap">
            <p class="mobile-list-card__title">{{ $title }}</p>
            @if(filled($meta))
                <p class="mobile-list-card__meta">{{ $meta }}</p>
            @endif
        </div>
        @if(filled($badge))
            <span class="status-badge status-{{ $badgeTone }}">{{ $badge }}</span>
        @endif
    </div>
    @if(filled($detail))
        <p class="mobile-list-card__detail">{{ $detail }}</p>
    @endif
    @isset($actions)
        <div class="mobile-list-card__actions" data-row-ignore>
            {!! $actions !!}
        </div>
    @endisset
</article>
