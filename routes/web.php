<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AddressImportController;
use App\Http\Controllers\AddressRequestController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RbacController;
use App\Rbac;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('home')
        : view('landing');
})->name('landing');

Auth::routes();

Route::middleware('auth')->group(function () {
    Route::get('/home', [DashboardController::class, 'index'])->name('home');
    Route::get('home/map', [DashboardController::class, 'map'])->name('home.map');

    Route::get('addresses', [AddressController::class, 'index'])->name('addresses.index');
    Route::get('addresses/create', [AddressController::class, 'create'])->name('addresses.create');
    Route::post('addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::get('addresses/users/{user}', [AddressController::class, 'forUser'])->name('addresses.user');

    // Above the {address} routes so 'import' is read as the literal segment it
    // is rather than as an id.
    Route::get('addresses/import', [AddressImportController::class, 'create'])->name('addresses.import.create');
    Route::post('addresses/import', [AddressImportController::class, 'store'])->name('addresses.import.store');
    Route::get('addresses/import/template', [AddressImportController::class, 'template'])->name('addresses.import.template');

    Route::get('addresses/{address}/edit', [AddressController::class, 'edit'])->name('addresses.edit');
    Route::put('addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::delete('addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
    Route::post('addresses/{address}/default', [AddressController::class, 'setDefault'])->name('addresses.default');

    // The change-request queue. Both roles reach the same screen; the scoping
    // inside the controller is what separates a Customer's own list from a
    // reader's queue.
    Route::get('requests', [AddressRequestController::class, 'index'])->name('requests.index');
    Route::get('requests/create', [AddressRequestController::class, 'create'])->name('requests.create');
    Route::post('requests', [AddressRequestController::class, 'store'])->name('requests.store');
    Route::post('requests/{addressRequest}/approve', [AddressRequestController::class, 'approve'])->name('requests.approve');
    Route::post('requests/{addressRequest}/reject', [AddressRequestController::class, 'reject'])->name('requests.reject');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // No model to write a policy about, so the gate is the middleware.
    Route::get('audit', [AuditLogController::class, 'index'])
        ->middleware('can:'.Rbac::AUDIT_PERMISSION)
        ->name('audit.index');

    Route::get('geo/cities', [GeoController::class, 'cities'])->name('geo.cities');

    Route::middleware('can:rbac.manage')->group(function () {
        Route::get('rbac', [RbacController::class, 'index'])->name('rbac.index');
        Route::post('rbac/permissions', [RbacController::class, 'updatePermissions'])->name('rbac.permissions');
        Route::post('rbac/users/{user}/role', [RbacController::class, 'assignRole'])->name('rbac.users.role');
    });
});
