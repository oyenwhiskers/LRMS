<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Shelf extends Model
{
    public const QR_PREFIX = 'LRMS:SHELF:1:';

    protected $fillable = ['rack_id', 'code', 'name', 'is_active'];

    protected static function booted(): void
    {
        static::creating(function (Shelf $shelf): void {
            $shelf->qr_identifier ??= (string) Str::uuid();
        });

        static::updating(function (Shelf $shelf): void {
            if ($shelf->isDirty('qr_identifier')) {
                throw new \DomainException('Shelf QR identifiers cannot be changed.');
            }
        });
    }

    public function qrPayload(): string
    {
        return self::QR_PREFIX.$this->qr_identifier;
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function legalFiles(): HasMany
    {
        return $this->hasMany(LegalFile::class);
    }

    public function getFullLocationAttribute(): string
    {
        $rack = $this->rack;

        if (! $rack) {
            return '';
        }

        $shelfLabel = $this->name ?: $this->code;

        return "{$rack->code} / {$shelfLabel}";
    }
}
