<?php

namespace App\Http\Controllers;

use App\Models\FileMovement;
use App\Models\LegalFile;
use App\Models\MovementBatch;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    public function returnForm(Request $request): View
    {
        abort_unless($request->user()->can('movements.return'), 403);

        return view('movements.scan', ['type' => 'return']);
    }

    public function history(Request $request): View
    {
        abort_unless($request->user()->can('movements.history'), 403);
        $movements = FileMovement::with(['legalFile', 'employee', 'previousHolder', 'processor', 'shelf.cabinet.room'])
            ->latest('occurred_at')->paginate(30);

        return view('movements.history', compact('movements'));
    }

    public function storeBorrow(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('movements.borrow'), 403);
        [$employee, $identifiers] = $this->validatedScan($request);
        $batch = $this->process($employee, $identifiers, 'borrow', $request);

        return redirect()->route('movements.borrow')->with('success', "{$batch->movements()->count()} file(s) borrowed by {$employee->full_name}.");
    }

    public function storeReturn(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('movements.return'), 403);
        [$returner, $identifiers] = $this->validatedScan($request);
        $batch = $this->process($returner, $identifiers, 'return', $request);
        $locations = $batch->movements()->with('shelf.cabinet.room')->get()->pluck('shelf.full_location')->filter()->unique()->join('; ');

        return redirect()->route('movements.return')->with('success', "{$batch->movements()->count()} file(s) returned. Store at: {$locations}");
    }

    private function validatedScan(Request $request): array
    {
        $data = $request->validate([
            'employee_qr' => ['required', 'uuid'],
            'file_qrs' => ['required', 'string'],
        ]);
        $employee = Staff::where('qr_identifier', $data['employee_qr'])->first();
        if (! $employee || ! $employee->is_active) {
            throw ValidationException::withMessages(['employee_qr' => 'Employee QR is invalid or inactive.']);
        }
        $identifiers = collect(preg_split('/[\s,]+/', trim($data['file_qrs'])))
            ->filter()->unique()->values();
        if ($identifiers->isEmpty()) {
            throw ValidationException::withMessages(['file_qrs' => 'Scan at least one file QR.']);
        }

        return [$employee, $identifiers];
    }

    private function process(Staff $employee, Collection $identifiers, string $type, Request $request): MovementBatch
    {
        return DB::transaction(function () use ($employee, $identifiers, $type, $request): MovementBatch {
            $files = LegalFile::query()->active()->whereIn('qr_identifier', $identifiers)->lockForUpdate()->get();
            if ($files->count() !== $identifiers->count()) {
                throw ValidationException::withMessages(['file_qrs' => 'One or more file QR codes are invalid or archived.']);
            }
            foreach ($files as $file) {
                $expected = $type === 'borrow' ? LegalFile::STATUS_AVAILABLE : LegalFile::STATUS_BORROWED;
                if ($file->status !== $expected) {
                    throw ValidationException::withMessages([
                        'file_qrs' => "{$file->reference_number} is {$file->status} and cannot be {$type}ed.",
                    ]);
                }
            }
            $now = now();
            $batch = MovementBatch::create([
                'type' => $type,
                'employee_id' => $employee->id,
                'processed_by' => $request->user()->id,
                'processed_at' => $now,
            ]);
            foreach ($files as $file) {
                $newStatus = $type === 'borrow' ? LegalFile::STATUS_BORROWED : LegalFile::STATUS_AVAILABLE;
                FileMovement::create([
                    'batch_id' => $batch->id,
                    'legal_file_id' => $file->id,
                    'type' => $type,
                    'employee_id' => $employee->id,
                    'previous_holder_id' => $file->current_holder_id,
                    'processed_by' => $request->user()->id,
                    'shelf_id' => $file->shelf_id,
                    'previous_status' => $file->status,
                    'new_status' => $newStatus,
                    'occurred_at' => $now,
                ]);
                $file->update([
                    'status' => $newStatus,
                    'current_holder_id' => $type === 'borrow' ? $employee->id : null,
                    'updated_by' => $request->user()->id,
                ]);
            }

            return $batch;
        }, 3);
    }
}
