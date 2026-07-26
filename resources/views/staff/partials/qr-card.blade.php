<div class="staff-qr-card">
    <div class="staff-qr-card__header">
        <div>
            <p class="staff-qr-card__eyebrow">Tsang &amp; Co.</p>
            <p class="staff-qr-card__subtitle">Legal Records Management System</p>
        </div>
        <div class="staff-qr-card__chip">Staff Pass</div>
    </div>
    <div class="staff-qr-card__body">
        <div class="staff-qr-card__details">
            <div class="staff-qr-card__field">
                <p class="staff-qr-card__label">Name</p>
                <p class="staff-qr-card__value">{{ $staffMember->full_name }}</p>
            </div>
            <div class="staff-qr-card__field">
                <p class="staff-qr-card__label">Staff number</p>
                <p class="staff-qr-card__value">{{ $staffMember->staff_number }}</p>
            </div>
            <div class="staff-qr-card__field">
                <p class="staff-qr-card__label">Position</p>
                <p class="staff-qr-card__value">{{ $staffMember->position?->name ?? 'Staff' }}</p>
            </div>
            <div class="staff-qr-card__footer-copy">
                <strong>LRMS</strong>
                <p>Scan to identify</p>
            </div>
        </div>
        <div class="staff-qr-card__qr-panel">
            <div class="staff-qr-card__qr-frame">
                <img class="staff-qr-card__qr-image" src="{{ $qr }}" alt="Employee QR code">
            </div>
            <div class="staff-qr-card__qr-caption">
                <span>Secure internal identity code</span>
            </div>
        </div>
    </div>
</div>
