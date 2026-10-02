<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\RbacController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('addresses.index')
        : view('landing');
})->name('landing');

Auth::routes();

Route::middleware('auth')->group(function () {
    Route::get('/home', fn () => redirect()->route('addresses.index'))->name('home');

    Route::get('addresses', [AddressController::class, 'index'])->name('addresses.index');
    Route::get('addresses/create', [AddressController::class, 'create'])->name('addresses.create');
    Route::post('addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::get('addresses/{address}/edit', [AddressController::class, 'edit'])->name('addresses.edit');
    Route::put('addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::delete('addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');

    Route::get('addresses/map', [AddressController::class, 'map'])->name('addresses.map');

    Route::get('geo/cities', [GeoController::class, 'cities'])->name('geo.cities');

    Route::middleware('can:rbac.manage')->group(function () {
        Route::get('rbac', [RbacController::class, 'index'])->name('rbac.index');
        Route::post('rbac/permissions', [RbacController::class, 'updatePermissions'])->name('rbac.permissions');
        Route::post('rbac/users/{user}/role', [RbacController::class, 'assignRole'])->name('rbac.users.role');
    });
});
