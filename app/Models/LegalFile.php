<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LegalFile extends Model
{
    public const STATUS_REGISTERED = 'registered';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_STORED = 'stored';

    public const STATUS_BORROWED = 'borrowed';

    public const STATUS_MISSING = 'missing';

    public const TYPE_LOAN = 'loan';

    public const TYPE_SNP = 'snp';

    public const TYPE_LITIGATION = 'litigation';

    protected $fillable = [
        'reference_number', 'purchaser', 'vendor', 'bank', 'property', 'title_number',
        'suite_number', 'ic_number', 'case_category_id', 'case_type', 'matter_type',
        'start_date', 'end_date', 'person_in_charge_id', 'shelf_id',
        'current_holder_id', 'status', 'archived_at', 'created_by', 'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (LegalFile $file): void {
            $file->qr_identifier ??= (string) Str::uuid();
            $file->file_identifier ??= self::nextIdentifier();
            $file->status ??= self::STATUS_REGISTERED;
        });
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public static function nextIdentifier(): string
    {
        return self::formatIdentifier(self::reserveIdentifierRange(1));
    }

    public static function reserveIdentifierRange(int $count): int
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('The identifier range must contain at least one value.');
        }

        return DB::transaction(function () use ($count): int {
            DB::table('system_sequences')->insertOrIgnore([
                'name' => 'legal_file',
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sequence = DB::table('system_sequences')->where('name', 'legal_file')->lockForUpdate()->first();
            DB::table('system_sequences')->where('name', 'legal_file')->update([
                'next_value' => $sequence->next_value + $count,
                'updated_at' => now(),
            ]);

            return (int) $sequence->next_value;
        });
    }

    public static function formatIdentifier(int $value): string
    {
        return 'FILE'.str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query;
    }

    public function caseCategory(): BelongsTo
    {
        return $this->belongsTo(CaseCategory::class);
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

    public function parties(): HasMany
    {
        return $this->hasMany(LegalFileParty::class)->orderBy('sort_order');
    }

    /**
     * @param  list<array{name?: string|null, ic_number?: string|null}>  $borrowers
     */
    public function syncBorrowers(array $borrowers): void
    {
        $normalized = collect($borrowers)
            ->map(fn (array $borrower): array => [
                'name' => trim((string) ($borrower['name'] ?? '')),
                'ic_number' => ($ic = trim((string) ($borrower['ic_number'] ?? ''))) !== '' ? $ic : null,
            ])
            ->filter(fn (array $borrower): bool => $borrower['name'] !== '')
            ->values();

        $this->parties()->delete();

        foreach ($normalized as $index => $borrower) {
            $this->parties()->create([
                'name' => $borrower['name'],
                'ic_number' => $borrower['ic_number'],
                'is_primary' => $index === 0,
                'sort_order' => $index,
            ]);
        }

        $this->forceFill([
            'purchaser' => self::joinPartyNames($normalized->pluck('name')->all()),
        ])->save();
    }

    /**
     * @param  list<string>  $names
     */
    public static function joinPartyNames(array $names): string
    {
        return collect($names)
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->implode(' & ');
    }

    /** Main party only — stickers omit co-borrowers (`purchaser` stores "A & B"). */
    public function primaryPurchaserName(): string
    {
        $purchaser = trim((string) ($this->purchaser ?? ''));

        return $purchaser === '' ? '' : trim(explode(' & ', $purchaser, 2)[0]);
    }

    /** Front sticker fourth line: bank / title no. / suite by case type. */
    public function stickerDetail(): string
    {
        return trim((string) match ($this->case_type) {
            self::TYPE_LOAN => $this->bank ?? '',
            self::TYPE_LITIGATION => $this->suite_number ?? '',
            default => $this->title_number ?? '',
        });
    }

    public function linkedAsPrimary(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'legal_file_links',
            'legal_file_id',
            'related_legal_file_id',
        )->withTimestamps();
    }

    public function linkedAsRelated(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'legal_file_links',
            'related_legal_file_id',
            'legal_file_id',
        )->withTimestamps();
    }

    public function relatedFiles(): Collection
    {
        return $this->linkedAsPrimary->merge($this->linkedAsRelated)->unique('id')->sortBy('reference_number')->values();
    }

    public function syncRelatedFiles(array $relatedIds): void
    {
        $relatedIds = collect($relatedIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== (int) $this->id)
            ->unique()
            ->values();

        DB::table('legal_file_links')
            ->where('legal_file_id', $this->id)
            ->orWhere('related_legal_file_id', $this->id)
            ->delete();

        $now = now();
        $rows = $relatedIds->map(function (int $relatedId) use ($now): array {
            $left = min((int) $this->id, $relatedId);
            $right = max((int) $this->id, $relatedId);

            return [
                'legal_file_id' => $left,
                'related_legal_file_id' => $right,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        if ($rows) {
            DB::table('legal_file_links')->insert($rows);
        }
    }

    public function categoryLabel(): string
    {
        return $this->caseCategory?->name
            ?? $this->matter_type
            ?? '—';
    }

    public static function caseTypes(): array
    {
        return [
            self::TYPE_LOAN,
            self::TYPE_SNP,
            self::TYPE_LITIGATION,
        ];
    }

    public function caseTypeLabel(): string
    {
        return match ($this->case_type) {
            self::TYPE_LOAN => 'Loan',
            self::TYPE_SNP => 'S&P',
            self::TYPE_LITIGATION => 'Litigation',
            default => str($this->case_type)->headline()->toString(),
        };
    }

    public function partyNameLabel(): string
    {
        return match ($this->case_type) {
            self::TYPE_LOAN => 'Borrower',
            self::TYPE_LITIGATION => "Person's name",
            default => 'Purchaser',
        };
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_REGISTERED,
            self::STATUS_PENDING,
            self::STATUS_COMPLETED,
            self::STATUS_STORED,
            self::STATUS_BORROWED,
            self::STATUS_MISSING,
        ];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_REGISTERED => 'Registered',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_STORED => 'Stored',
            self::STATUS_BORROWED => 'Borrowed',
            self::STATUS_MISSING => 'Missing',
            default => str($this->status)->headline()->toString(),
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::STATUS_REGISTERED => 'neutral',
            self::STATUS_PENDING => 'pending',
            self::STATUS_COMPLETED => 'ready',
            self::STATUS_STORED => 'approved',
            self::STATUS_BORROWED => 'borrowed',
            self::STATUS_MISSING => 'rejected',
            default => 'neutral',
        };
    }
}
