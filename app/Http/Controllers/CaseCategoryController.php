<?php

namespace App\Http\Controllers;

use App\Models\CaseCategory;
use App\Models\LegalFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CaseCategoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('categories.view'), 403);

        $query = CaseCategory::query()->withCount('legalFiles');

        if ($search = trim((string) $request->input('search'))) {
            $contains = '%'.addcslashes($search, '\\%_').'%';
            $query->where(function ($builder) use ($contains): void {
                $builder->where('code', 'like', $contains)
                    ->orWhere('name', 'like', $contains);
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        return view('categories.index', [
            'categories' => $query->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('categories.manage'), 403);
        $data = $this->validated($request);
        CaseCategory::create($data);

        return back()->with('success', 'Case category created.');
    }

    public function update(Request $request, CaseCategory $category): RedirectResponse
    {
        abort_unless($request->user()->can('categories.manage'), 403);
        $data = $this->validated($request, $category);

        $previousName = $category->name;
        $previousCode = $category->code;
        $nameChanged = $previousName !== $data['name'];
        $codeChanged = $previousCode !== $data['code'];
        $linkedCount = $category->legalFiles()->count();

        if (! $nameChanged && ! $codeChanged) {
            return back()->with('success', 'Case category updated.');
        }

        if ($linkedCount > 0 && ! $request->boolean('confirm_linked_updates')) {
            throw ValidationException::withMessages([
                'category' => "This category has {$linkedCount} linked file(s). Confirm the update to proceed.",
            ]);
        }

        $rewritten = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $category,
            $data,
            $nameChanged,
            &$rewritten,
            &$skipped,
        ): void {
            [$rewritten, $skipped] = $this->syncLinkedFiles(
                $category,
                $data['code'],
                $data['name'],
                $nameChanged,
            );

            $category->update($data);
        });

        $message = 'Case category updated.';
        if ($linkedCount > 0) {
            $parts = [];
            if ($nameChanged) {
                $parts[] = 'category label synced';
            }
            if ($rewritten > 0) {
                $parts[] = "{$rewritten} file number".($rewritten === 1 ? '' : 's').' updated';
            }
            if ($skipped > 0) {
                $parts[] = "{$skipped} non-standard number".($skipped === 1 ? '' : 's').' left unchanged';
            }
            if ($parts !== []) {
                $message .= ' ('.implode('; ', $parts).').';
            }
        }

        return back()->with('success', $message);
    }

    public function toggle(Request $request, CaseCategory $category): RedirectResponse
    {
        abort_unless($request->user()->can('categories.manage'), 403);
        $category->update(['is_active' => ! $category->is_active]);

        return back()->with('success', 'Case category status updated.');
    }

    public function destroy(Request $request, CaseCategory $category): RedirectResponse
    {
        abort_unless($request->user()->can('categories.manage'), 403);

        if ($category->legalFiles()->exists()) {
            return back()->withErrors([
                'category' => "Cannot delete {$category->code} because files are still linked to it. Deactivate it instead, or reassign those files first.",
            ]);
        }

        $sequenceName = $category->sequenceName();
        $category->delete();
        DB::table('system_sequences')->where('name', $sequenceName)->delete();

        return back()->with('success', 'Case category deleted.');
    }

    /**
     * Sync matter_type and rewrite standard CODE/YEAR/NUMBER references to the new code.
     *
     * @return array{0: int, 1: int} Rewritten count, skipped count
     */
    private function syncLinkedFiles(
        CaseCategory $category,
        string $newCode,
        string $newName,
        bool $updateMatterType,
    ): array {
        $files = $category->legalFiles()->get(['id', 'reference_number', 'matter_type']);
        /** @var array<int, array{matter_type?: string, reference_number?: string}> $pending */
        $pending = [];
        /** @var array<int, string> $plannedReferences */
        $plannedReferences = [];
        $skipped = 0;

        foreach ($files as $file) {
            $updates = [];

            if ($updateMatterType && $file->matter_type !== $newName) {
                $updates['matter_type'] = $newName;
            }

            if (preg_match('/^([A-Za-z0-9]+)\/(\d{4})\/(\d+)$/', (string) $file->reference_number, $matches)) {
                $newReference = sprintf('%s/%s/%04d', $newCode, $matches[2], (int) $matches[3]);

                if ($newReference !== $file->reference_number) {
                    $updates['reference_number'] = $newReference;
                    $plannedReferences[$file->id] = $newReference;
                }
            } else {
                $skipped++;
            }

            if ($updates !== []) {
                $pending[$file->id] = $updates;
            }
        }

        if ($plannedReferences !== []) {
            $duplicateTargets = array_keys(array_filter(
                array_count_values($plannedReferences),
                fn (int $count): bool => $count > 1,
            ));

            if ($duplicateTargets !== []) {
                throw ValidationException::withMessages([
                    'code' => 'Cannot update file numbers: this change would create duplicate references ('.implode(', ', $duplicateTargets).').',
                ]);
            }

            $conflicts = LegalFile::query()
                ->whereIn('reference_number', array_values($plannedReferences))
                ->whereNotIn('id', array_keys($plannedReferences))
                ->orderBy('reference_number')
                ->pluck('reference_number')
                ->all();

            if ($conflicts !== []) {
                throw ValidationException::withMessages([
                    'code' => 'Cannot update file numbers: these references already exist ('.implode(', ', $conflicts).').',
                ]);
            }
        }

        $rewritten = 0;
        foreach ($pending as $fileId => $updates) {
            if (isset($updates['reference_number'])) {
                $rewritten++;
            }

            LegalFile::query()->whereKey($fileId)->update($updates);
        }

        if ($plannedReferences !== []) {
            $maxNumber = 0;
            foreach ($plannedReferences as $reference) {
                if (preg_match('/\/(\d+)$/', $reference, $matches)) {
                    $maxNumber = max($maxNumber, (int) $matches[1]);
                }
            }

            $sequenceName = $category->sequenceName();
            DB::table('system_sequences')->insertOrIgnore([
                'name' => $sequenceName,
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $current = (int) DB::table('system_sequences')->where('name', $sequenceName)->value('next_value');
            DB::table('system_sequences')->where('name', $sequenceName)->update([
                'next_value' => max($current, $maxNumber + 1),
                'updated_at' => now(),
            ]);
        }

        return [$rewritten, $skipped];
    }

    private function validated(Request $request, ?CaseCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('case_categories', 'code')->ignore($category),
            ],
            'confirm_linked_updates' => ['sometimes', 'boolean'],
        ]);

        $data['code'] = strtoupper($data['code']);
        unset($data['confirm_linked_updates']);

        return $data;
    }
}
