<?php

namespace App\Http\Controllers;

use App\Models\FileMovement;
use App\Models\LegalFile;
use App\Models\MovementBatch;
use App\Models\Shelf;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MovementController extends Controller
{
    public function borrow(Request $request): View
    {
        abort_unless($request->user()->can('movements.borrow'), 403);

        return view('movements.scan', ['type' => 'borrow']);
    }

    public function intake(Request $request): View
    {
        abort_unless($request->user()->can('movements.pic_intake'), 403);

        return view('movements.scan', [
            'type' => 'store',
            'returnFile' => $this->resolveReturnFile($request),
        ]);
    }

    public function returnForm(Request $request): View
    {
        abort_unless($request->user()->can('movements.pic_return_receive'), 403);

        return view('movements.scan', ['type' => 'return']);
    }

    public function history(Request $request): View|JsonResponse
    {
        abort_unless($request->user()->can('movements.history'), 403);

        $isFeed = $request->boolean('feed');
        $perPage = $isFeed ? 10 : 30;
        $period = $request->string('period')->value() ?: 'all';
        $selectedDate = $request->string('date')->value();
        $dateFrom = $request->string('date_from')->value();
        $dateTo = $request->string('date_to')->value();

        $movementsQuery = FileMovement::with(['legalFile', 'employee', 'receiver', 'previousHolder', 'processor', 'shelf.rack']);

        match ($period) {
            'today' => $movementsQuery->whereBetween('occurred_at', [now()->startOfDay(), now()->endOfDay()]),
            'this_week' => $movementsQuery->whereBetween('occurred_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'this_month' => $movementsQuery->whereBetween('occurred_at', [now()->startOfMonth(), now()->endOfMonth()]),
            'this_year' => $movementsQuery->whereBetween('occurred_at', [now()->startOfYear(), now()->endOfYear()]),
            'specific_date' => $this->applySpecificDateFilter($movementsQuery, $selectedDate),
            'custom_range' => $this->applyCustomRangeFilter($movementsQuery, $dateFrom, $dateTo),
            default => null,
        };

        if ($search = trim((string) $request->input('search'))) {
            $contains = '%'.addcslashes($search, '\\%_').'%';
            $movementsQuery->where(function ($builder) use ($contains): void {
                $builder->whereHas('legalFile', function ($file) use ($contains): void {
                    $file->where('reference_number', 'like', $contains)
                        ->orWhere('file_identifier', 'like', $contains)
                        ->orWhere('purchaser', 'like', $contains);
                })
                    ->orWhereHas('employee', fn ($staff) => $staff->where('full_name', 'like', $contains))
                    ->orWhereHas('receiver', fn ($staff) => $staff->where('full_name', 'like', $contains))
                    ->orWhereHas('previousHolder', fn ($staff) => $staff->where('full_name', 'like', $contains))
                    ->orWhereHas('processor', fn ($user) => $user->where('name', 'like', $contains));
            });
        }

        $movementTypes = $this->historyMovementTypes();
        $movementType = trim((string) $request->input('movement'));
        if ($movementType !== '' && array_key_exists($movementType, $movementTypes)) {
            $movementsQuery->where('type', $movementType);
        } else {
            $movementType = '';
        }

        $movements = $movementsQuery
            ->latest('occurred_at')
            ->paginate($perPage)
            ->withQueryString();

        if ($isFeed) {
            return response()->json([
                'html' => view('movements.partials.mobile-history-cards', [
                    'movements' => $movements,
                ])->render(),
                'next_page' => $movements->hasMorePages() ? $movements->currentPage() + 1 : null,
            ]);
        }

        return view('movements.history', [
            'movements' => $movements,
            'period' => $period,
            'periodLabel' => $this->historyPeriodLabel($period, $selectedDate, $dateFrom, $dateTo),
            'selectedDate' => $selectedDate,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'movementType' => $movementType,
            'movementTypes' => $movementTypes,
        ]);
    }

    private function historyPeriodLabel(string $period, ?string $selectedDate, ?string $dateFrom, ?string $dateTo): string
    {
        return match ($period) {
            'today' => 'Today',
            'this_week' => 'This week',
            'this_month' => 'This month',
            'this_year' => 'This year',
            'all' => 'All history',
            'specific_date' => $selectedDate
                ? Carbon::parse($selectedDate)->format('d M Y')
                : 'Specific date',
            'custom_range' => ($dateFrom && $dateTo)
                ? Carbon::parse($dateFrom)->format('d M Y').' – '.Carbon::parse($dateTo)->format('d M Y')
                : 'Custom range',
            default => 'All history',
        };
    }

    /**
     * @return array<string, string>
     */
    private function historyMovementTypes(): array
    {
        return [
            'registered' => 'Registered',
            'borrow' => 'Borrow',
            'return' => 'Return',
            'stored' => 'Stored',
            'completion_requested' => 'Completion Requested',
            'completion_approved' => 'Completion Approved',
            'completion_rejected' => 'Completion Rejected',
            'missing' => 'Missing',
            'found' => 'Found',
        ];
    }

    private function applySpecificDateFilter($query, ?string $selectedDate): void
    {
        if (! $selectedDate) {
            return;
        }

        $date = Carbon::parse($selectedDate);
        $query->whereBetween('occurred_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
    }

    private function applyCustomRangeFilter($query, ?string $dateFrom, ?string $dateTo): void
    {
        if ($dateFrom) {
            $query->where('occurred_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if ($dateTo) {
            $query->where('occurred_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }
    }

    public function storeBorrow(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('movements.borrow'), 403);
        $borrower = $this->resolveStaff($request->input('employee_qr'), 'employee_qr');
        $identifiers = $this->validatedIdentifiers($request->input('file_qrs'));
        $batch = DB::transaction(function () use ($borrower, $identifiers, $request): MovementBatch {
            $files = $this->lockedFiles($identifiers);
            $this->assertStatuses($files, LegalFile::STATUS_STORED, 'borrowed');
            $now = now();
            $batch = MovementBatch::create([
                'type' => 'borrow',
                'employee_id' => $borrower->id,
                'processed_by' => $request->user()->id,
                'processed_at' => $now,
            ]);

            foreach ($files as $file) {
                FileMovement::create([
                    'batch_id' => $batch->id,
                    'legal_file_id' => $file->id,
                    'type' => 'borrow',
                    'employee_id' => $borrower->id,
                    'previous_holder_id' => $file->current_holder_id,
                    'processed_by' => $request->user()->id,
                    'shelf_id' => $file->shelf_id,
                    'previous_status' => $file->status,
                    'new_status' => LegalFile::STATUS_BORROWED,
                    'occurred_at' => $now,
                ]);

                $file->update([
                    'status' => LegalFile::STATUS_BORROWED,
                    'current_holder_id' => $borrower->id,
                    'updated_by' => $request->user()->id,
                ]);
            }

            return $batch;
        }, 3);

        return redirect()->route('movements.borrow')->with('success', "{$batch->movements()->count()} file(s) borrowed by {$borrower->full_name}.");
    }

    public function storeIntake(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('movements.pic_intake'), 403);
        $operator = $this->authenticatedOperatorStaff($request);
        $identifiers = $this->validatedIdentifiers($request->input('file_qrs'));
        $shelf = $this->validatedShelfQr($request->input('shelf_qr'), 'shelf_qr');

        $batch = DB::transaction(function () use ($operator, $identifiers, $shelf, $request): MovementBatch {
            $files = $this->lockedFiles($identifiers);
            $this->assertStatuses($files, LegalFile::STATUS_COMPLETED, 'stored');
            $now = now();
            $batch = MovementBatch::create([
                'type' => 'pic_intake',
                'employee_id' => $operator->id,
                'processed_by' => $request->user()->id,
                'processed_at' => $now,
            ]);

            foreach ($files as $file) {
                FileMovement::create([
                    'batch_id' => $batch->id,
                    'legal_file_id' => $file->id,
                    'type' => 'stored',
                    'employee_id' => null,
                    'receiver_id' => null,
                    'processed_by' => $request->user()->id,
                    'shelf_id' => $shelf->id,
                    'previous_status' => $file->status,
                    'new_status' => LegalFile::STATUS_STORED,
                    'occurred_at' => $now,
                ]);

                $file->update([
                    'status' => LegalFile::STATUS_STORED,
                    'shelf_id' => $shelf->id,
                    'current_holder_id' => null,
                    'updated_by' => $request->user()->id,
                ]);
            }

            return $batch;
        }, 3);

        $returnFile = $this->resolveReturnFile($request);
        $success = "{$batch->movements()->count()} file(s) stored successfully.";

        if ($returnFile) {
            return redirect()
                ->route('files.show', $returnFile)
                ->with('success', $success);
        }

        return redirect()->route('movements.pic-intake')->with('success', $success);
    }

    public function storeReturn(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('movements.pic_return_receive'), 403);
        $pic = $this->resolveStaff($request->input('pic_qr'), 'pic_qr');
        $returner = $this->resolveStaff($request->input('returner_qr'), 'returner_qr');
        $identifiers = $this->validatedIdentifiers($request->input('file_qrs'));

        $batch = DB::transaction(function () use ($pic, $returner, $identifiers, $request): MovementBatch {
            $files = $this->lockedFiles($identifiers);
            $this->assertStatuses($files, LegalFile::STATUS_BORROWED, 'returned');
            $now = now();
            $batch = MovementBatch::create([
                'type' => 'return',
                'employee_id' => $pic->id,
                'processed_by' => $request->user()->id,
                'processed_at' => $now,
            ]);

            foreach ($files as $file) {
                FileMovement::create([
                    'batch_id' => $batch->id,
                    'legal_file_id' => $file->id,
                    'type' => 'return',
                    'employee_id' => $returner->id,
                    'receiver_id' => $pic->id,
                    'previous_holder_id' => $file->current_holder_id,
                    'processed_by' => $request->user()->id,
                    'shelf_id' => null,
                    'previous_status' => $file->status,
                    'new_status' => LegalFile::STATUS_COMPLETED,
                    'occurred_at' => $now,
                ]);

                $file->update([
                    'status' => LegalFile::STATUS_COMPLETED,
                    'shelf_id' => null,
                    'current_holder_id' => null,
                    'updated_by' => $request->user()->id,
                ]);
            }

            return $batch;
        }, 3);

        return redirect()->route('movements.return')->with('success', "{$batch->movements()->count()} file(s) received by {$pic->full_name} from {$returner->full_name}.");
    }

    private function resolveStaff(?string $identifier, string $field): Staff
    {
        if (! is_string($identifier) || ! str($identifier)->isUuid()) {
            throw ValidationException::withMessages([$field => 'A valid staff QR is required.']);
        }

        $staff = Staff::where('qr_identifier', $identifier)->first();
        if (! $staff || ! $staff->is_active) {
            throw ValidationException::withMessages([$field => 'Staff QR is invalid or inactive.']);
        }

        return $staff;
    }

    private function validatedIdentifiers(?string $value): Collection
    {
        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages(['file_qrs' => 'Scan at least one file QR.']);
        }

        $identifiers = collect(preg_split('/[\s,]+/', trim($value)))
            ->filter()
            ->unique()
            ->values();

        if ($identifiers->isEmpty()) {
            throw ValidationException::withMessages(['file_qrs' => 'Scan at least one file QR.']);
        }

        return $identifiers;
    }

    private function lockedFiles(Collection $identifiers, string $field = 'file_qrs'): Collection
    {
        $files = LegalFile::query()->active()->whereIn('qr_identifier', $identifiers)->lockForUpdate()->get();
        if ($files->count() !== $identifiers->count()) {
            throw ValidationException::withMessages([$field => 'One or more file QR codes are invalid.']);
        }

        return $files;
    }

    private function assertStatuses(Collection $files, string $expected, string $action, string $field = 'file_qrs'): void
    {
        foreach ($files as $file) {
            if ($file->status !== $expected) {
                throw ValidationException::withMessages([
                    $field => "{$file->reference_number} is {$file->statusLabel()} and cannot be {$action}.",
                ]);
            }
        }
    }

    private function validatedShelfQr(?string $payload, string $field): Shelf
    {
        if (! is_string($payload) || ! str_starts_with($payload, Shelf::QR_PREFIX)) {
            throw ValidationException::withMessages([$field => 'Scan a valid shelf QR code.']);
        }

        $identifier = substr($payload, strlen(Shelf::QR_PREFIX));
        if (! str($identifier)->isUuid()) {
            throw ValidationException::withMessages([$field => 'Scan a valid shelf QR code.']);
        }

        $shelf = Shelf::with('rack')->where('qr_identifier', $identifier)->first();
        if (! $shelf || ! $shelf->is_active || ! $shelf->rack?->is_active) {
            throw ValidationException::withMessages([$field => 'The scanned shelf is unavailable or inactive.']);
        }

        return $shelf;
    }

    private function authenticatedOperatorStaff(Request $request): Staff
    {
        $staff = $request->user()->staff;

        if (! $staff || ! $staff->is_active) {
            throw ValidationException::withMessages([
                'operator' => 'Your account must be linked to an active staff record to complete this action.',
            ]);
        }

        return $staff;
    }

    private function resolveReturnFile(Request $request): ?LegalFile
    {
        if (! $request->user()->can('files.view')) {
            return null;
        }

        $fileId = $request->input('return_file')
            ?? $request->old('return_file');

        if (! filled($fileId) || ! ctype_digit((string) $fileId)) {
            return null;
        }

        return LegalFile::query()->active()->find((int) $fileId);
    }
}
