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

    public const REVIEW_PENDING = 'pending';

    public const REVIEW_NEEDS_CORRECTION = 'needs_correction';

    public const REVIEW_VERIFIED = 'verified';

    protected $fillable = [
        'user_id',
        'document_path',
        'ai_suggestion',
        'legal_file_id',
        'shelf_id',
        'status',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'review_comment_log',
    ];

    protected function casts(): array
    {
        return [
            'ai_suggestion' => 'array',
            'reviewed_at' => 'datetime',
            'review_comment_log' => 'array',
        ];
    }

    /**
     * Correction comments newest-first. Falls back to the current review_notes row for legacy data.
     *
     * @return list<array{note: string, by: int|null, by_name: string|null, at: string|null}>
     */
    public function reviewCommentLogEntries(): array
    {
        $log = $this->review_comment_log;
        if (! is_array($log)) {
            $log = [];
        }

        $entries = [];
        foreach ($log as $row) {
            if (! is_array($row)) {
                continue;
            }
            $note = isset($row['note']) && is_string($row['note']) ? trim($row['note']) : '';
            if ($note === '') {
                continue;
            }
            $entries[] = [
                'note' => $note,
                'by' => isset($row['by']) && is_numeric($row['by']) ? (int) $row['by'] : null,
                'by_name' => isset($row['by_name']) && is_string($row['by_name']) ? $row['by_name'] : null,
                'at' => isset($row['at']) && is_string($row['at']) ? $row['at'] : null,
            ];
        }

        if ($entries === [] && filled($this->review_notes)) {
            $entries[] = [
                'note' => (string) $this->review_notes,
                'by' => $this->reviewed_by,
                'by_name' => $this->reviewedBy?->name,
                'at' => $this->reviewed_at?->toIso8601String(),
            ];
        }

        return array_reverse($entries);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
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

    public function reviewStatusLabel(): string
    {
        return match ($this->review_status) {
            self::REVIEW_PENDING => 'Belum semak',
            self::REVIEW_NEEDS_CORRECTION => 'Perlu pembetulan',
            self::REVIEW_VERIFIED => 'Disahkan',
            default => $this->review_status ?? self::REVIEW_PENDING,
        };
    }

    /**
     * @return list<string>
     */
    public static function reviewStatuses(): array
    {
        return [
            self::REVIEW_PENDING,
            self::REVIEW_NEEDS_CORRECTION,
            self::REVIEW_VERIFIED,
        ];
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
