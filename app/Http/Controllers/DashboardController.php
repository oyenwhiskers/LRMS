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
        return view('dashboard', [
            'position' => $request->user()->staff?->position,
            'counts' => [
                'total' => LegalFile::active()->count(),
                'available' => LegalFile::active()->where('status', LegalFile::STATUS_AVAILABLE)->count(),
                'borrowed' => LegalFile::active()->where('status', LegalFile::STATUS_BORROWED)->count(),
                'missing' => LegalFile::active()->where('status', LegalFile::STATUS_MISSING)->count(),
            ],
            'activities' => FileMovement::with(['legalFile', 'employee', 'processor'])->latest('occurred_at')->limit(10)->get(),
        ]);
    }
}
