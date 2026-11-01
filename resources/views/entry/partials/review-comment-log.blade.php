@php
    /** @var \App\Models\EntryScan $scan */
    $entries = $scan->reviewCommentLogEntries();
@endphp
@if ($entries !== [])
    <aside class="entry-review-note" role="status">
        <p class="entry-review-note__label">Sejarah catatan</p>
        <ol class="entry-review-note__log">
            @foreach ($entries as $entry)
                <li class="entry-review-note__log-item">
                    <p class="entry-review-note__meta">
                        {{ $entry['by_name'] ?: '—' }}
                        @if (! empty($entry['at']))
                            · {{ \App\Support\LocalDate::format($entry['at'], 'd/m/Y H:i') }}
                        @endif
                    </p>
                    <p class="entry-review-note__copy">{{ $entry['note'] }}</p>
                </li>
            @endforeach
        </ol>
    </aside>
@endif
