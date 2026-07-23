<?php

namespace App\Http\Controllers;

use App\Models\FileMovement;
use App\Models\LegalFile;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $counts = LegalFile::active()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS available',
                [LegalFile::STATUS_AVAILABLE],
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS borrowed',
                [LegalFile::STATUS_BORROWED],
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS missing',
                [LegalFile::STATUS_MISSING],
            )
            ->first();

        return view('dashboard', [
            'position' => $request->user()->staff?->position,
            'counts' => [
                'total' => (int) ($counts?->total ?? 0),
                'available' => (int) ($counts?->available ?? 0),
                'borrowed' => (int) ($counts?->borrowed ?? 0),
                'missing' => (int) ($counts?->missing ?? 0),
            ],
            'activities' => FileMovement::query()
                ->select(['id', 'legal_file_id', 'employee_id', 'processed_by', 'type', 'occurred_at'])
                ->with([
                    'legalFile:id,reference_number',
                    'employee:id,full_name',
                    'processor:id,name',
                ])
                ->latest('occurred_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
