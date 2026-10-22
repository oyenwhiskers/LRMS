@php
    $icon = $icon ?? 'files';
    $title = $title ?? 'No records found';
    $message = $message ?? null;
@endphp
<div class="list-page__empty" role="status">
    <span class="list-page__empty-icon" aria-hidden="true">
        @include('layouts.partials.sidebar-icon', ['icon' => $icon])
    </span>
    <p class="list-page__empty-title">{{ $title }}</p>
    @if(filled($message))
        <p class="list-page__empty-message">{{ $message }}</p>
    @endif
</div>
