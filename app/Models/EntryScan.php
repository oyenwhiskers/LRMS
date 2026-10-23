<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntryScan extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SAVED = 'saved';

    public const STATUS_STORED = 'stored';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'document_path',
        'ai_suggestion',
        'legal_file_id',
        'shelf_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'ai_suggestion' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legalFile(): BelongsTo
    {
        return $this->belongsTo(LegalFile::class);
    }

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(Shelf::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draf',
            self::STATUS_SAVED => 'Disimpan',
            self::STATUS_STORED => 'Di rak',
            self::STATUS_FAILED => 'Gagal',
            default => $this->status,
        };
    }
}
