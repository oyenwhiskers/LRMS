<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Stored scan images (page order). Falls back to document_path for legacy rows.
     *
     * @return list<string>
     */
    public function documentPaths(): array
    {
        $primary = is_string($this->document_path) ? trim($this->document_path) : '';
        if ($primary === '') {
            return [];
        }

        $disk = Storage::disk('local');
        $dir = dirname($primary);
        if ($dir === '.' || $dir === '' || $dir === DIRECTORY_SEPARATOR) {
            return $disk->exists($primary) ? [$primary] : [];
        }

        $files = collect($disk->files($dir))
            ->filter(fn (string $path): bool => (bool) preg_match('/\.(jpe?g|png|webp|gif)$/i', $path))
            ->sort(fn (string $a, string $b): int => strnatcasecmp($a, $b))
            ->values()
            ->all();

        if ($files === []) {
            return $disk->exists($primary) ? [$primary] : [];
        }

        return $files;
    }
}
