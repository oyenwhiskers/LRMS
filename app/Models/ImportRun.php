<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ImportRun extends Model
{
    protected $fillable = [
        'identifier', 'original_filename', 'status', 'total_rows',
        'imported_rows', 'failed_rows', 'errors', 'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(fn (ImportRun $run) => $run->identifier ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['errors' => 'array'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
