<div class="shelf-label">
    <img class="shelf-label__qr" src="{{ $label['qr'] }}" alt="QR code for shelf {{ $rack->code }}/{{ $label['shelf']->code }}">
    <div class="shelf-label__code">{{ $rack->code }}/{{ $label['shelf']->code }}</div>
    <div class="shelf-label__room">{{ config('lrms.storage.room_name') }}</div>
    <div class="shelf-label__brand">TSANG &amp; CO.</div>
</div>
