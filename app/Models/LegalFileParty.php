<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalFileParty extends Model
{
    protected $fillable = [
        'legal_file_id',
        'name',
        'ic_number',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function legalFile(): BelongsTo
    {
        return $this->belongsTo(LegalFile::class);
    }
}
