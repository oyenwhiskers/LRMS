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
    </div>
    <div class="staff-qr-card__qr-panel">
        <img class="staff-qr-card__qr-image" src="{{ $qr }}" alt="Employee QR code">
    </div>
</div>
