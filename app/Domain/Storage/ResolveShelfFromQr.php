<?php

namespace App\Domain\Storage;

use App\Models\Shelf;
use Illuminate\Validation\ValidationException;

class ResolveShelfFromQr
{
    public function handle(?string $payload, string $field = 'shelf_qr'): Shelf
    {
        if (! is_string($payload) || ! str_starts_with($payload, Shelf::QR_PREFIX)) {
            throw ValidationException::withMessages([$field => 'Scan a valid shelf QR code.']);
        }

        $identifier = substr($payload, strlen(Shelf::QR_PREFIX));
        if (! str($identifier)->isUuid()) {
            throw ValidationException::withMessages([$field => 'Scan a valid shelf QR code.']);
        }

        $shelf = Shelf::with('rack')->where('qr_identifier', $identifier)->first();
        if (! $shelf || ! $shelf->is_active || ! $shelf->rack?->is_active) {
            throw ValidationException::withMessages([$field => 'The scanned shelf is unavailable or inactive.']);
        }

        return $shelf;
    }
}
