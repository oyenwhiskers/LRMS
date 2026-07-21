<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LegalFile extends Model
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_BORROWED = 'borrowed';

    public const STATUS_MISSING = 'missing';

    protected $fillable = [
        'reference_number', 'loan_reference', 'purchaser', 'vendor', 'property',
        'matter_type', 'important_date', 'person_in_charge_id', 'shelf_id',
        'current_holder_id', 'status', 'archived_at', 'created_by', 'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (LegalFile $file): void {
            $file->qr_identifier ??= (string) Str::uuid();
            $file->file_identifier ??= self::nextIdentifier();
        });
    }

    protected function casts(): array
    {
        return ['important_date' => 'date', 'archived_at' => 'datetime'];
    }

    public static function nextIdentifier(): string
    {
        $next = DB::transaction(function (): int {
            DB::table('system_sequences')->insertOrIgnore([
                'name' => 'legal_file',
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sequence = DB::table('system_sequences')->where('name', 'legal_file')->lockForUpdate()->first();
            DB::table('system_sequences')->where('name', 'legal_file')->update([
                'next_value' => $sequence->next_value + 1,
                'updated_at' => now(),
            ]);

            return (int) $sequence->next_value;
        });

        return 'FILE'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(Shelf::class);
    }

    public function currentHolder(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'current_holder_id');
    }

    public function personInCharge(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'person_in_charge_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FileMovement::class)->latest('occurred_at');
    }
}
