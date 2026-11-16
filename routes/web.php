<?php

use App\Http\Controllers\SetupController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\StaffSessionController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/t/{token}', [PageController::class, 'table'])->name('table.order');   // QR code target

// Staff session (PIN)
Route::get('/login', [StaffSessionController::class, 'create'])->name('staff.login');
Route::post('/login', [StaffSessionController::class, 'store']);
Route::post('/logout', [StaffSessionController::class, 'destroy'])->name('staff.logout');

// Staff screens
Route::middleware('staff')->group(function () {
    Route::get('/setup', [SetupController::class, 'show'])->name('setup');
    Route::post('/setup', [SetupController::class, 'store']);
    Route::get('/pos', [PageController::class, 'pos'])->name('pos');
    Route::get('/kitchen', [PageController::class, 'kitchen'])->name('kitchen');
    Route::get('/admin', [PageController::class, 'admin'])->name('admin');
});
