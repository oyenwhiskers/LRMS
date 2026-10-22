@php
    $purchaser = trim((string) ($file->purchaser ?? ''));
@endphp
{{-- The side sticker remains 80 x 40 mm, with a rotated 40 x 80 mm spine layout. --}}
<div class="file-label file-label--side">
    <div class="file-label--side__canvas">
        <table class="file-label--side__table" role="presentation">
            <tr>
                <td class="file-label--side__brand">Tsang &amp; Co.</td>
            </tr>
            <tr>
                <td class="file-label--side__reference">{{ $file->reference_number }}</td>
            </tr>
            <tr>
                <td class="file-label--side__purchaser">{{ $purchaser !== '' ? $purchaser : '—' }}</td>
            </tr>
            <tr>
                <td class="file-label--side__file-id">{{ $file->file_identifier }}</td>
            </tr>
            <tr>
                <td class="file-label--side__qr-cell">
                    <div class="file-label--side__qr-group">
                        <img class="file-label--side__qr" src="{{ $qr }}" alt="File QR code" width="72" height="72">
                        <span class="file-label--side__qr-caption">Scan file</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</div>
