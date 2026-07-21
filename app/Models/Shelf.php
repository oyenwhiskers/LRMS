<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shelf extends Model
{
    protected $fillable = ['cabinet_id', 'code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
    }

    public function legalFiles(): HasMany
    {
        return $this->hasMany(LegalFile::class);
    }

    public function getFullLocationAttribute(): string
    {
        return collect([$this->cabinet?->room?->name, $this->cabinet?->name, $this->name])
            ->filter()->join(' / ');
    }
}
