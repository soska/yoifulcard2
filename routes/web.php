<?php

use App\Http\Controllers\Admin\ClaimSuperadminController;
use App\Http\Controllers\Dashboard\CardController;
use App\Http\Controllers\Dashboard\CardEmailController;
use App\Http\Controllers\Dashboard\CardLedgerController;
use App\Http\Controllers\Dashboard\CardQrController;
use App\Http\Controllers\Dashboard\TransactionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Dashboard: members of an organization.
Route::middleware(['auth', 'organization'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('cards', [CardController::class, 'index'])->name('cards.index');
    Route::get('cards/create', [CardController::class, 'create'])->name('cards.create');
    Route::get('cards/{card}', [CardController::class, 'show'])->whereUuid('card')->name('cards.show');
    Route::get('cards/{card}/qr.svg', [CardQrController::class, 'svg'])->whereUuid('card')->name('cards.qr.svg');
    Route::get('cards/{card}/qr.png', [CardQrController::class, 'png'])->whereUuid('card')->name('cards.qr.png');

    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/export', [TransactionController::class, 'export'])->name('transactions.export');

    // Writes: refused while the organization is suspended or cancelled.
    Route::middleware('organization.writable')->group(function () {
        Route::post('cards', [CardController::class, 'store'])->name('cards.store');
        Route::post('cards/{card}/freeze', [CardController::class, 'freeze'])->whereUuid('card')->name('cards.freeze');
        Route::post('cards/{card}/unfreeze', [CardController::class, 'unfreeze'])->whereUuid('card')->name('cards.unfreeze');
        Route::patch('cards/{card}/email', CardEmailController::class)->whereUuid('card')->name('cards.email');

        // Ledger: every balance change goes through CardLedger.
        Route::post('cards/{card}/load', [CardLedgerController::class, 'load'])->whereUuid('card')->name('cards.load');
        Route::post('cards/{card}/spend', [CardLedgerController::class, 'spend'])->whereUuid('card')->name('cards.spend');
        Route::post('cards/{card}/adjust', [CardLedgerController::class, 'adjust'])->whereUuid('card')->name('cards.adjust');
    });
});

// Reader: members of an organization. The scanner itself arrives in Phase 4.
Route::middleware(['auth', 'organization'])->group(function () {
    Route::inertia('scan', 'reader/scan')->name('scan');
});

// Admin: superadmins only, except the one-time claim.
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('claim', ClaimSuperadminController::class)->name('claim');

    Route::middleware('superadmin')->group(function () {
        Route::inertia('/', 'admin/index')->name('index');
    });
});

require __DIR__.'/settings.php';
