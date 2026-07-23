<?php

namespace App\Http\Controllers;

use App\Models\FileMovement;
use App\Models\LegalFile;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();

        return view('dashboard', [
            'today' => [
                'borrowed' => FileMovement::query()
                    ->where('type', 'borrow')
                    ->whereBetween('occurred_at', [$todayStart, $todayEnd])
                    ->count(),
                'returned' => FileMovement::query()
                    ->where('type', 'return')
                    ->whereBetween('occurred_at', [$todayStart, $todayEnd])
                    ->count(),
                'registered' => LegalFile::active()
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->count(),
                'still_out' => LegalFile::active()
                    ->where('status', LegalFile::STATUS_BORROWED)
                    ->count(),
            ],
            'activities' => FileMovement::query()
                ->select(['id', 'legal_file_id', 'employee_id', 'processed_by', 'type', 'occurred_at'])
                ->with([
                    'legalFile:id,reference_number,matter_type,vendor,current_holder_id,status',
                    'legalFile.currentHolder:id,full_name,position_id',
                    'legalFile.currentHolder.position:id,name',
                    'employee:id,full_name,position_id',
                    'employee.position:id,name',
                ])
                ->latest('occurred_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
