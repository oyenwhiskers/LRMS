@php
    $purchaser = trim((string) ($file->purchaser ?? ''));
    $property = trim((string) ($file->property ?? ''));
@endphp
<div class="file-label file-label--front">
    <table class="file-label__table" role="presentation">
        <colgroup>
            <col style="width:90mm">
            <col style="width:44mm">
        </colgroup>
        <tr>
            <td colspan="2" class="file-label__brand">Tsang &amp; Co.</td>
        </tr>
        <tr>
            <td class="file-label__info">
                <p class="file-label__reference">{{ $file->reference_number }}</p>
                <p class="file-label__purchaser">{{ $purchaser !== '' ? $purchaser : '—' }}</p>
                <p class="file-label__property">{{ $property !== '' ? $property : '—' }}</p>
            </td>
            <td class="file-label__qr-cell">
                <div class="file-label__qr-group">
                    <img class="file-label__qr" src="{{ $qr }}" alt="File QR code" width="159" height="159">
                    <span class="file-label__qr-caption">Scan file</span>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="file-label__file-id">{{ $file->file_identifier }}</td>
        </tr>
    </table>
</div>
