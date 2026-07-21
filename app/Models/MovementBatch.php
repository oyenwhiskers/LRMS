<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MovementBatch extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'type', 'employee_id', 'processed_by', 'processed_at'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \DomainException('Movement batches are immutable.'));
        static::deleting(fn () => throw new \DomainException('Movement batches are immutable.'));
    }

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'employee_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FileMovement::class, 'batch_id');
    }
}
