<?php

use App\Http\Controllers\Admin\EntryUserController;
use App\Http\Controllers\Admin\PositionController;
use App\Http\Controllers\Admin\RegistrationApprovalController;
use App\Http\Controllers\Entry\AiTokenUsageController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CaseCategoryController;
use App\Http\Controllers\CompletionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Entry\EntryAuthController;
use App\Http\Controllers\Entry\EntryController;
use App\Http\Controllers\Entry\EntryOperatorController;
use App\Http\Controllers\Entry\EntryPrintStationController;
use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\LegalFileController;
use App\Http\Controllers\Entry\ManageOperatorController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\StorageLabelController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    $user = auth()->user();
    if (! $user->isApproved()) {
        return redirect()->route('account.status');
    }

    return redirect()->route($user->isEntry() ? 'entry.home' : 'dashboard');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:8,1');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:5,10');

    Route::prefix('entry')->name('entry.')->group(function (): void {
        Route::get('/login', [EntryAuthController::class, 'createLogin'])->name('login');
        Route::post('/login', [EntryAuthController::class, 'storeLogin'])
            ->middleware('throttle:8,1')
            ->name('login.store');
        Route::get('/register', [EntryAuthController::class, 'createRegister'])->name('register');
        Route::post('/register', [EntryAuthController::class, 'storeRegister'])
            ->middleware('throttle:5,10')
            ->name('register.store');
    });
});

Route::middleware('auth')->group(function (): void {
    Route::view('/account-status', 'auth.account-status')->name('account.status');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('entry')->prefix('entry')->name('entry.')->group(function (): void {
        Route::get('/', [EntryController::class, 'home'])->name('home');
        Route::get('/history', [EntryController::class, 'history'])->name('history');
        Route::get('/operators', [EntryOperatorController::class, 'index'])->name('operators.index');
        Route::get('/operators/scans/{scan}/document', [EntryOperatorController::class, 'document'])
            ->name('operators.document');
        Route::post('/operators/scans/{scan}/review', [EntryOperatorController::class, 'setReview'])
            ->name('operators.review');
        Route::get('/scan', [EntryController::class, 'create'])->name('scan');
        Route::post('/scan', [EntryController::class, 'store'])->name('scan.store');
        Route::post('/confirm', [EntryController::class, 'save'])->name('save');
        Route::get('/scan/shelves/{identifier}', [EntryController::class, 'lookupShelf'])->name('shelves.lookup');
        Route::get('/scan/{scan}/shelf', [EntryController::class, 'shelf'])->name('shelf');
        Route::post('/scan/{scan}/shelf', [EntryController::class, 'storeShelf'])->name('shelf.store');
        Route::get('/scan/{scan}/document', [EntryController::class, 'document'])->name('scan.document');
        Route::put('/scan/{scan}', [EntryController::class, 'update'])->name('scan.update');
        // POST path (not DELETE verb): proxies in front of the app have been 404'ing spoofed DELETE.
        Route::post('/scan/{scan}/delete', [EntryController::class, 'destroy'])->name('scan.destroy');
        Route::get('/files/{file}/label', [EntryController::class, 'label'])->name('files.label');
        Route::get('/files/{file}/label.pdf', [EntryController::class, 'labelPdf'])->name('files.label.pdf');

        Route::get('/print-station', [EntryPrintStationController::class, 'index'])->name('print-station');
        Route::get('/print-jobs', [EntryPrintStationController::class, 'list'])->name('print-jobs.index');
        Route::post('/print-jobs', [EntryPrintStationController::class, 'store'])->name('print-jobs.store');
        Route::post('/print-jobs/status', [EntryPrintStationController::class, 'setStatus'])->name('print-jobs.status');
        Route::get('/print-jobs/batch-print', [EntryPrintStationController::class, 'batchPrint'])->name('print-jobs.batch-print');
        Route::delete('/print-jobs/{job}', [EntryPrintStationController::class, 'destroy'])->name('print-jobs.destroy');

        Route::get('/ai-usage/unlock', [AiTokenUsageController::class, 'unlockForm'])->name('ai-usage.unlock');
        Route::post('/ai-usage/unlock', [AiTokenUsageController::class, 'unlock'])
            ->middleware('throttle:8,1')
            ->name('ai-usage.unlock.store');
        Route::get('/ai-usage', [AiTokenUsageController::class, 'index'])
            ->middleware('ai_usage')
            ->name('ai-usage.index');

        Route::prefix('manage-operators')->name('manage-operators.')->group(function (): void {
            Route::get('/unlock', [ManageOperatorController::class, 'unlockForm'])->name('unlock');
            Route::post('/unlock', [ManageOperatorController::class, 'unlock'])
                ->middleware('throttle:8,1')
                ->name('unlock.store');
            Route::middleware('manage_operator')->group(function (): void {
                Route::get('/', [ManageOperatorController::class, 'index'])->name('index');
                Route::get('/{user}/password', [ManageOperatorController::class, 'editPassword'])->name('password.edit');
                Route::put('/{user}/password', [ManageOperatorController::class, 'updatePassword'])->name('password.update');
            });
        });
    });

    Route::middleware(['approved', 'not_entry'])->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/account/password', [PasswordController::class, 'edit'])->name('account.password.edit');
        Route::put('/account/password', [PasswordController::class, 'update'])->name('account.password.update');

        Route::prefix('admin')->name('admin.')->group(function (): void {
            Route::get('/registrations', [RegistrationApprovalController::class, 'index'])
                ->name('registrations.index');
            Route::patch('/registrations/{registration}', [RegistrationApprovalController::class, 'update'])
                ->name('registrations.update');
            Route::resource('positions', PositionController::class)->except('show');

            Route::get('/entry-users', [EntryUserController::class, 'index'])->name('entry-users.index');
            Route::post('/entry-users', [EntryUserController::class, 'store'])->name('entry-users.store');
            Route::patch('/entry-users/{entryUser}/deactivate', [EntryUserController::class, 'deactivate'])
                ->name('entry-users.deactivate');
            Route::patch('/entry-users/{entryUser}/reactivate', [EntryUserController::class, 'reactivate'])
                ->name('entry-users.reactivate');
        });

        Route::resource('staff', StaffController::class)->except(['show', 'destroy']);
        Route::patch('/staff/{staff}/status', [StaffController::class, 'toggle'])->name('staff.toggle');
        Route::get('/staff/{staff}/qr', [StaffController::class, 'qr'])->name('staff.qr');
        Route::get('/scan/staff/{identifier}', [StaffController::class, 'lookup'])->name('scan.staff');

        Route::get('/categories', [CaseCategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CaseCategoryController::class, 'store'])->name('categories.store');
        Route::patch('/categories/{category}', [CaseCategoryController::class, 'update'])->name('categories.update');
        Route::patch('/categories/{category}/status', [CaseCategoryController::class, 'toggle'])->name('categories.toggle');
        Route::delete('/categories/{category}', [CaseCategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/storage', [StorageController::class, 'index'])->name('storage.index');
        Route::post('/storage/racks', [StorageController::class, 'storeRack'])->name('storage.racks.store');
        Route::get('/storage/racks/{rack}', [StorageController::class, 'show'])->name('storage.racks.show');
        Route::get('/storage/racks/{rack}/label', [StorageLabelController::class, 'rack'])->name('storage.racks.label');
        Route::get('/storage/racks/{rack}/label.pdf', [StorageLabelController::class, 'rackPdf'])->name('storage.racks.label.pdf');
        Route::get('/storage/racks/{rack}/shelf-labels', [StorageLabelController::class, 'shelves'])->name('storage.racks.shelves-labels');
        Route::get('/storage/racks/{rack}/shelf-labels.pdf', [StorageLabelController::class, 'shelvesPdf'])->name('storage.racks.shelves-labels.pdf');
        Route::get('/storage/shelves/{shelf}/label', [StorageLabelController::class, 'shelf'])->name('storage.shelves.label');
        Route::get('/storage/shelves/{shelf}/label.pdf', [StorageLabelController::class, 'shelfPdf'])->name('storage.shelves.label.pdf');
        Route::get('/scan/shelves/{identifier}', [StorageController::class, 'lookupShelf'])->name('scan.shelves');
        Route::patch('/storage/racks/{rack}', [StorageController::class, 'updateRack'])->name('storage.racks.update');
        Route::delete('/storage/racks/{rack}', [StorageController::class, 'destroyRack'])->name('storage.racks.destroy');
        Route::patch('/storage/racks/{rack}/status', [StorageController::class, 'toggleRack'])->name('storage.racks.toggle');
        Route::post('/storage/racks/{rack}/shelves', [StorageController::class, 'storeShelf'])->name('storage.shelves.store');
        Route::patch('/storage/shelves/{shelf}', [StorageController::class, 'updateShelf'])->name('storage.shelves.update');
        Route::delete('/storage/shelves/{shelf}', [StorageController::class, 'destroyShelf'])->name('storage.shelves.destroy');
        Route::patch('/storage/shelves/{shelf}/status', [StorageController::class, 'toggleShelf'])->name('storage.shelves.toggle');

        Route::resource('files', LegalFileController::class);
        Route::get('/files/{file}/label', [LegalFileController::class, 'label'])->name('files.label');
        Route::get('/files/{file}/label.pdf', [LegalFileController::class, 'labelPdf'])->name('files.label.pdf');
        Route::patch('/files/{file}/missing', [LegalFileController::class, 'missing'])->name('files.missing');
        Route::patch('/files/{file}/found', [LegalFileController::class, 'found'])->name('files.found');
        Route::get('/scan/files/{identifier}', [LegalFileController::class, 'lookup'])->name('scan.files');

        Route::get('/completions', [CompletionController::class, 'index'])->name('completions.index');
        Route::post('/files/{file}/completion/request', [CompletionController::class, 'request'])->name('completions.request');
        Route::post('/files/{file}/completion/approve', [CompletionController::class, 'approve'])->name('completions.approve');
        Route::post('/files/{file}/completion/reject', [CompletionController::class, 'reject'])->name('completions.reject');

        Route::get('/borrow', [MovementController::class, 'borrow'])->name('movements.borrow');
        Route::post('/borrow', [MovementController::class, 'storeBorrow'])->name('movements.borrow.store');
        Route::get('/pic-intake', [MovementController::class, 'intake'])->name('movements.pic-intake');
        Route::post('/pic-intake', [MovementController::class, 'storeIntake'])->name('movements.pic-intake.store');
        Route::get('/return', [MovementController::class, 'returnForm'])->name('movements.return');
        Route::post('/return', [MovementController::class, 'storeReturn'])->name('movements.return.store');
        Route::get('/movements', [MovementController::class, 'history'])->name('movements.history');

        Route::get('/imports', [ImportExportController::class, 'index'])->name('imports.index');
        Route::post('/imports/preview', [ImportExportController::class, 'preview'])->name('imports.preview');
        Route::post('/imports/confirm', [ImportExportController::class, 'confirm'])->name('imports.confirm');
        Route::get('/imports/template', [ImportExportController::class, 'template'])->name('imports.template');
        Route::get('/imports/{run}/errors', [ImportExportController::class, 'errors'])->name('imports.errors');
        Route::get('/exports/files', [ImportExportController::class, 'files'])->name('exports.files');
        Route::get('/exports/movements', [ImportExportController::class, 'movements'])->name('exports.movements');
        Route::get('/exports/dashboard/{section}', [ImportExportController::class, 'dashboard'])->name('exports.dashboard');
    });
});
