<?php

use App\Http\Controllers\Api\EntitlementController;
use App\Http\Controllers\Api\RegulatoryReadModelController;
use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/index.html');
});

Route::prefix('api')->group(function (): void {
    Route::get('/auth/setup-status', [SessionController::class, 'setupStatus'])
        ->name('auth.setup-status');
    Route::post('/auth/login', [SessionController::class, 'store'])
        ->middleware('throttle:5,1')->name('auth.login');

    Route::middleware(['auth', 'pi.tenant'])->group(function (): void {
        Route::get('/auth/session', [SessionController::class, 'show'])->name('auth.session');
        Route::post('/auth/logout', [SessionController::class, 'destroy'])->name('auth.logout');
        Route::get('/qa-subscription-r69e/entitlements', EntitlementController::class)
            ->name('pi.entitlements');
        Route::get('/qa-regulatory-r70g/laravel-read-model', RegulatoryReadModelController::class)
            ->name('pi.regulatory.read-model');
    });
});
