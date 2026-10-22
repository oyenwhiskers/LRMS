<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rack extends Model
{
    protected $fillable = ['code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function shelves(): HasMany
    {
        return $this->hasMany(Shelf::class);
    }
}
