<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileMovement extends Model
{
    protected $fillable = [
        'batch_id', 'legal_file_id', 'type', 'employee_id', 'receiver_id', 'previous_holder_id',
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

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'receiver_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(Shelf::class);
    }

    public function typeLabel(): string
    {
        return (string) str($this->type)->headline();
    }

    public function historyPersonLabel(): ?string
    {
        return match (true) {
            $this->type === 'return' && ($this->employee || $this->receiver) => collect([
                $this->employee?->full_name,
                $this->receiver ? 'PIC '.$this->receiver->full_name : null,
            ])->filter()->implode(' · ') ?: null,
            (bool) $this->employee => $this->employee->full_name,
            (bool) $this->previousHolder => $this->previousHolder->full_name,
            default => null,
        };
    }

    public function historyShelfLabel(): ?string
    {
        if (! $this->shelf?->full_location) {
            return null;
        }

        return match ($this->type) {
            'borrow' => 'From '.$this->shelf->full_location,
            'stored', 'return' => 'To '.$this->shelf->full_location,
            default => $this->shelf->full_location,
        };
    }

    public function historyBadgeTone(): string
    {
        return match ($this->type) {
            'borrow' => 'borrowed',
            'return', 'found', 'completion_approved', 'stored' => 'approved',
            'completion_requested', 'registered' => 'pending',
            'missing', 'completion_rejected' => 'rejected',
            default => 'neutral',
        };
    }
}
