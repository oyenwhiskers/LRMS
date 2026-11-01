@php
    /** @var \App\Models\EntryScan $scan */
    $entries = $scan->reviewCommentLogEntries();
    $total = count($entries);
@endphp
@if ($entries !== [])
    <aside class="entry-review-log" role="status" aria-label="Sejarah catatan">
        <div class="entry-review-log__head">
            <p class="entry-review-log__label">Sejarah catatan</p>
            <span class="entry-review-log__count">{{ $total }} catatan</span>
        </div>
        <ol class="entry-review-log__list">
            @foreach ($entries as $index => $entry)
                <li class="entry-review-log__item">
                    <div class="entry-review-log__rail" aria-hidden="true">
                        <span class="entry-review-log__dot"></span>
                    </div>
                    <div class="entry-review-log__body">
                        <div class="entry-review-log__meta">
                            <span class="entry-review-log__who">{{ $entry['by_name'] ?: '—' }}</span>
                            @if (! empty($entry['at']))
                                <time class="entry-review-log__when" datetime="{{ $entry['at'] }}">
                                    {{ \App\Support\LocalDate::format($entry['at'], 'd/m/Y H:i') }}
                                </time>
                            @endif
                        </div>
                        <p class="entry-review-log__note">{{ $entry['note'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </aside>
@endif
