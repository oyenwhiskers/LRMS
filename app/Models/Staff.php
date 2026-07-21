<?php

namespace App\Models;

use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Staff extends Model
{
    /** @use HasFactory<StaffFactory> */
    use HasFactory;

    protected $fillable = ['staff_number', 'full_name', 'email', 'phone', 'position_id', 'is_active'];

    protected static function booted(): void
    {
        static::creating(function (Staff $staff): void {
            $staff->qr_identifier ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function heldFiles(): HasMany
    {
        return $this->hasMany(LegalFile::class, 'current_holder_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FileMovement::class, 'employee_id');
    }
}
