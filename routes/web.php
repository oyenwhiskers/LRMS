<?php

use App\Http\Controllers\Admin\PositionController;
use App\Http\Controllers\Admin\RegistrationApprovalController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\LegalFileController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StorageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route(auth()->user()->isApproved() ? 'dashboard' : 'account.status')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:8,1');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:5,10');
});

Route::middleware('auth')->group(function (): void {
    Route::view('/account-status', 'auth.account-status')->name('account.status');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('approved')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)
            ->middleware('can:dashboard.view')
            ->name('dashboard');

        Route::prefix('admin')->name('admin.')->group(function (): void {
            Route::get('/registrations', [RegistrationApprovalController::class, 'index'])
                ->name('registrations.index');
            Route::patch('/registrations/{registration}', [RegistrationApprovalController::class, 'update'])
                ->name('registrations.update');
            Route::resource('positions', PositionController::class)->except('show');
        });

        Route::resource('staff', StaffController::class)->except(['show', 'destroy']);
        Route::patch('/staff/{staff}/status', [StaffController::class, 'toggle'])->name('staff.toggle');
        Route::get('/staff/{staff}/qr', [StaffController::class, 'qr'])->name('staff.qr');
        Route::get('/scan/staff/{identifier}', [StaffController::class, 'lookup'])->name('scan.staff');

        Route::get('/storage', [StorageController::class, 'index'])->name('storage.index');
        Route::post('/storage/rooms', [StorageController::class, 'storeRoom'])->name('storage.rooms.store');
        Route::post('/storage/cabinets', [StorageController::class, 'storeCabinet'])->name('storage.cabinets.store');
        Route::post('/storage/shelves', [StorageController::class, 'storeShelf'])->name('storage.shelves.store');
        Route::patch('/storage/rooms/{room}', [StorageController::class, 'updateRoom'])->name('storage.rooms.update');
        Route::patch('/storage/cabinets/{cabinet}', [StorageController::class, 'updateCabinet'])->name('storage.cabinets.update');
        Route::patch('/storage/shelves/{shelf}', [StorageController::class, 'updateShelf'])->name('storage.shelves.update');
        Route::patch('/storage/rooms/{room}/status', [StorageController::class, 'toggleRoom'])->name('storage.rooms.toggle');
        Route::patch('/storage/cabinets/{cabinet}/status', [StorageController::class, 'toggleCabinet'])->name('storage.cabinets.toggle');
        Route::patch('/storage/shelves/{shelf}/status', [StorageController::class, 'toggleShelf'])->name('storage.shelves.toggle');

        Route::resource('files', LegalFileController::class)->except('destroy');
        Route::patch('/files/{file}/archive', [LegalFileController::class, 'archive'])->name('files.archive');
        Route::get('/files/{file}/label', [LegalFileController::class, 'label'])->name('files.label');
        Route::get('/files/{file}/label.pdf', [LegalFileController::class, 'labelPdf'])->name('files.label.pdf');
        Route::patch('/files/{file}/missing', [LegalFileController::class, 'missing'])->name('files.missing');
        Route::patch('/files/{file}/found', [LegalFileController::class, 'found'])->name('files.found');
        Route::get('/scan/files/{identifier}', [LegalFileController::class, 'lookup'])->name('scan.files');

        Route::get('/borrow', [MovementController::class, 'borrow'])->name('movements.borrow');
        Route::post('/borrow', [MovementController::class, 'storeBorrow'])->name('movements.borrow.store');
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
    });
});
