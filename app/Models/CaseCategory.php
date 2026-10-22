<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CaseCategory extends Model
{
    protected $fillable = ['name', 'code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function legalFiles(): HasMany
    {
        return $this->hasMany(LegalFile::class);
    }

    public function sequenceName(): string
    {
        return 'case_category_'.$this->id;
    }

    public function reserveNextNumber(int $count = 1): int
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('The sequence range must contain at least one value.');
        }

        return DB::transaction(function () use ($count): int {
            DB::table('system_sequences')->insertOrIgnore([
                'name' => $this->sequenceName(),
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('system_sequences')
                ->where('name', $this->sequenceName())
                ->lockForUpdate()
                ->first();

            DB::table('system_sequences')->where('name', $this->sequenceName())->update([
                'next_value' => $sequence->next_value + $count,
                'updated_at' => now(),
            ]);

            return (int) $sequence->next_value;
        });
    }

    public function formatReference(int $number, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        return sprintf('%s/%d/%04d', strtoupper($this->code), $year, $number);
    }

    public function nextReference(): string
    {
        return $this->formatReference($this->reserveNextNumber());
    }
}
