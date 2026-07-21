<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileMovement extends Model
{
    protected $fillable = [
        'batch_id', 'legal_file_id', 'type', 'employee_id', 'previous_holder_id',
        'processed_by', 'shelf_id', 'previous_status', 'new_status', 'notes', 'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \DomainException('File movement records are immutable.'));
        static::deleting(fn () => throw new \DomainException('File movement records are immutable.'));
    }

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function legalFile(): BelongsTo
    {
        return $this->belongsTo(LegalFile::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'employee_id');
    }

    public function previousHolder(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'previous_holder_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(Shelf::class);
    }
}
