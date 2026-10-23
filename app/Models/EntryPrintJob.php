<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntryPrintJob extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PRINTED = 'printed';

    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_FRONT = 'front';

    public const TYPE_SIDE = 'side';

    protected $fillable = [
        'legal_file_id',
        'requested_by',
        'type',
        'status',
        'printed_at',
        'printed_by',
    ];

    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
        ];
    }

    public function legalFile(): BelongsTo
    {
        return $this->belongsTo(LegalFile::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPrinted(): bool
    {
        return $this->status === self::STATUS_PRINTED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Belum dicetak',
            self::STATUS_PRINTED => 'Dicetak',
            self::STATUS_CANCELLED => 'Dibatalkan',
            default => str($this->status)->headline()->toString(),
        };
    }
}
