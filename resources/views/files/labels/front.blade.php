@php
    $purchaser = $file->primaryPurchaserName();
    $property = trim((string) ($file->property ?? ''));
    $detail = $file->stickerDetail();
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
                @if ($property !== '')
                    <p class="file-label__property">{{ $property }}</p>
                @endif
                @if ($detail !== '')
                    <p class="file-label__detail">{{ $detail }}</p>
                @endif
            </td>
            <td class="file-label__qr-cell">
                <div class="file-label__qr-group">
                    <img class="file-label__qr" src="{{ $qr }}" alt="File QR code" width="159" height="159">
                    <span class="file-label__qr-caption">Scan file</span>
                </div>
            </td>
        </tr>
    </table>
</div>
