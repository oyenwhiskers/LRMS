<div class="file-label-card">
    <div class="file-label-card__header">
        <div>
            <p class="file-label-card__eyebrow">Tsang &amp; Co.</p>
            <p class="file-label-card__subtitle">Legal Records Management System</p>
        </div>
        <div class="file-label-card__chip">{{ $file->file_identifier }}</div>
    </div>
    <div class="file-label-card__body">
        <div class="file-label-card__details">
            <div class="file-label-card__field">
                <p class="file-label-card__label">Reference No.</p>
                <p class="file-label-card__reference">{{ $file->reference_number }}</p>
            </div>
            <div class="file-label-card__field">
                <p class="file-label-card__label">Purchaser</p>
                <p class="file-label-card__value">{{ $file->purchaser }}</p>
            </div>
            <div class="file-label-card__field">
                <p class="file-label-card__label">Property</p>
                <p class="file-label-card__value">{{ $file->property }}</p>
            </div>
        </div>
        <div class="file-label-card__qr-panel">
            <div class="file-label-card__qr-frame">
                <img class="file-label-card__qr-image" src="{{ $qr }}" alt="File QR code">
            </div>
            <p class="file-label-card__qr-caption">Scan file identity</p>
        </div>
    </div>
</div>
