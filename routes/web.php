<?php

use App\Http\Controllers\Admin\ClaimSuperadminController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Dashboard: members of an organization.
Route::middleware(['auth', 'organization'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
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
