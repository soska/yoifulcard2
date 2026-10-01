<?php

use App\Http\Controllers\Admin\ClaimSuperadminController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\SuperadminController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\UserPasswordController;
use App\Http\Controllers\Dashboard\AnalyticsController;
use App\Http\Controllers\Dashboard\CardController;
use App\Http\Controllers\Dashboard\CardEmailController;
use App\Http\Controllers\Dashboard\CardLedgerController;
use App\Http\Controllers\Dashboard\CardLinkController;
use App\Http\Controllers\Dashboard\CardQrController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Dashboard\SwitchOrganizationController;
use App\Http\Controllers\Dashboard\TransactionController;
use App\Http\Controllers\Preferences\LocaleController;
use App\Http\Controllers\Preferences\ThemeController;
use App\Http\Controllers\PublicCardController;
use App\Http\Controllers\Reader\ScanController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Language and theme cookies, for guests and members alike.
Route::post('locale', LocaleController::class)->name('locale.update');
Route::post('theme', ThemeController::class)->name('theme.update');

// Public card: anyone with the link, no login. The token is not constrained
// here so malformed tokens get the same not-found page as unknown ones.
// The QR comes first: the card page's catch-all token would swallow it.
Route::get('c/{token}/qr.svg', [PublicCardController::class, 'qr'])->name('public-card.qr');
Route::get('c/{token}', [PublicCardController::class, 'show'])->where('token', '.*')->name('public-card.show');
Route::post('c/{token}/email', [PublicCardController::class, 'email'])
    ->middleware('throttle:public-card-email')
    ->name('public-card.email');

// Dashboard: members of an organization.
Route::middleware(['auth', 'organization'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('cards', [CardController::class, 'index'])->name('cards.index');
    Route::get('cards/create', [CardController::class, 'create'])->name('cards.create');
    Route::get('cards/{card}', [CardController::class, 'show'])->whereUuid('card')->name('cards.show');
    Route::get('cards/{card}/qr.svg', [CardQrController::class, 'svg'])->whereUuid('card')->name('cards.qr.svg');
    Route::get('cards/{card}/qr.png', [CardQrController::class, 'png'])->whereUuid('card')->name('cards.qr.png');
    Route::get('cards/{card}/link', [CardLinkController::class, 'show'])->whereUuid('card')->name('cards.link');

    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/export', [TransactionController::class, 'export'])->name('transactions.export');

    Route::get('analytics', AnalyticsController::class)->name('analytics');

    // Business settings. The profile, security and appearance pages are in
    // routes/settings.php.
    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');

    // Writes: refused while the organization is suspended or cancelled.
    Route::middleware('organization.writable')->group(function () {
        Route::post('cards', [CardController::class, 'store'])->name('cards.store');
        Route::post('cards/{card}/freeze', [CardController::class, 'freeze'])->whereUuid('card')->name('cards.freeze');
        Route::post('cards/{card}/unfreeze', [CardController::class, 'unfreeze'])->whereUuid('card')->name('cards.unfreeze');
        Route::patch('cards/{card}/email', CardEmailController::class)->whereUuid('card')->name('cards.email');
        Route::post('cards/{card}/link/email', [CardLinkController::class, 'email'])
            ->whereUuid('card')
            ->middleware('throttle:card-link-email')
            ->name('cards.link.email');

        // Ledger: every balance change goes through CardLedger.
        Route::post('cards/{card}/load', [CardLedgerController::class, 'load'])->whereUuid('card')->name('cards.load');
        Route::post('cards/{card}/spend', [CardLedgerController::class, 'spend'])->whereUuid('card')->name('cards.spend');
        Route::post('cards/{card}/adjust', [CardLedgerController::class, 'adjust'])->whereUuid('card')->name('cards.adjust');

        // Owners and managers only; the form requests check the role.
        Route::patch('settings/organization', [SettingsController::class, 'updateOrganization'])->name('settings.organization.update');
        Route::patch('settings/program', [SettingsController::class, 'updateProgram'])->name('settings.program.update');
    });
});

// Business switcher: only the user's own memberships. Not under
// `organization.writable`, so a user can leave a suspended business.
Route::middleware(['auth'])->group(function () {
    Route::post('organizations/{organization}/switch', SwitchOrganizationController::class)
        ->whereUuid('organization')
        ->name('organizations.switch');
});

// Reader: members of an organization. Charge and add funds post to the
// ledger routes above (cards.spend, cards.load).
Route::middleware(['auth', 'organization'])->group(function () {
    Route::get('scan', [ScanController::class, 'index'])->name('scan');
    Route::post('scan/lookup', [ScanController::class, 'lookup'])->name('scan.lookup');
    Route::get('scan/cards/{card}', [ScanController::class, 'show'])->whereUuid('card')->name('scan.cards.show');
});

// The reader's offline fallback. The service worker caches it at install.
Route::view('offline', 'offline')->name('offline');

// Admin: superadmins only, except the one-time claim.
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('claim', ClaimSuperadminController::class)->name('claim');

    Route::middleware('superadmin')->group(function () {
        Route::get('/', OverviewController::class)->name('index');

        Route::get('organizations', [AdminOrganizationController::class, 'index'])->name('organizations.index');
        Route::get('organizations/create', [AdminOrganizationController::class, 'create'])->name('organizations.create');
        Route::post('organizations', [AdminOrganizationController::class, 'store'])->name('organizations.store');
        Route::get('organizations/{organization}', [AdminOrganizationController::class, 'show'])->whereUuid('organization')->name('organizations.show');
        Route::patch('organizations/{organization}', [AdminOrganizationController::class, 'update'])->whereUuid('organization')->name('organizations.update');
        Route::post('organizations/{organization}/suspend', [AdminOrganizationController::class, 'suspend'])->whereUuid('organization')->name('organizations.suspend');
        Route::post('organizations/{organization}/reactivate', [AdminOrganizationController::class, 'reactivate'])->whereUuid('organization')->name('organizations.reactivate');

        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('users/{user}/password', UserPasswordController::class)->whereNumber('user')->name('users.password');
        Route::post('users/{user}/superadmin', [SuperadminController::class, 'store'])->whereNumber('user')->name('users.superadmin.store');
        Route::delete('users/{user}/superadmin', [SuperadminController::class, 'destroy'])->whereNumber('user')->name('users.superadmin.destroy');
    });
});

require __DIR__.'/settings.php';
