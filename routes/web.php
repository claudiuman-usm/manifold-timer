<?php

use App\Http\Controllers\KidAuthController;
use App\Http\Controllers\KidController;
use App\Http\Controllers\ParentAuthController;
use App\Http\Controllers\ParentController;
use Illuminate\Support\Facades\Route;

/* ---------------------------------------------------------------- */
/* Single PIN gate — nothing is shown until a valid PIN is entered   */
/* ---------------------------------------------------------------- */
Route::get('/', [KidAuthController::class, 'gate'])->name('picker');
Route::post('/enter', [KidAuthController::class, 'enter'])->name('gate.enter');
Route::post('/logout', [KidAuthController::class, 'logout'])->name('kid.logout');

/* ---------------------------------------------------------------- */
/* Kid timer (signed-in kid)                                        */
/* ---------------------------------------------------------------- */
Route::middleware('kid')->prefix('kid')->name('kid.')->group(function () {
    Route::get('/', [KidController::class, 'show'])->name('show');
    Route::get('/state', [KidController::class, 'state'])->name('state');
    Route::post('/start', [KidController::class, 'start'])->name('start');
    Route::post('/stop', [KidController::class, 'stop'])->name('stop');
    Route::post('/theme', [KidController::class, 'toggleTheme'])->name('theme');
    Route::post('/change-pin', [KidController::class, 'changePin'])->name('changePin');
    Route::get('/history', [KidController::class, 'history'])->name('history');
});

/* ---------------------------------------------------------------- */
/* Parent (signs in via the same PIN gate; parent PIN takes priority) */
/* ---------------------------------------------------------------- */
Route::get('/parent/login', fn () => redirect('/'))->name('parent.login');
Route::post('/parent/logout', [ParentAuthController::class, 'logout'])->name('parent.logout');

Route::middleware('parent')->prefix('parent')->name('parent.')->group(function () {
    Route::get('/', [ParentController::class, 'dashboard'])->name('dashboard');
    Route::get('/state', [ParentController::class, 'state'])->name('state');
    Route::get('/reports', [ParentController::class, 'reports'])->name('reports');

    Route::patch('/kids/{kid}', [ParentController::class, 'updateKid'])->name('kids.update');
    Route::patch('/kids/{kid}/cycle', [ParentController::class, 'updateKidCycle'])->name('kids.cycle.update');
    Route::post('/kids/{kid}/cycle/reset', [ParentController::class, 'resetKidCycle'])->name('kids.cycle.reset');
    Route::post('/kids/{kid}/open', [ParentController::class, 'openKid'])->name('kids.open');

    Route::post('/settings', [ParentController::class, 'updateSettings'])->name('settings.update');

    Route::post('/categories', [ParentController::class, 'storeCategory'])->name('categories.store');
    Route::patch('/categories/{category}', [ParentController::class, 'updateCategory'])->name('categories.update');
    Route::delete('/categories/{category}', [ParentController::class, 'destroyCategory'])->name('categories.destroy');

    Route::patch('/sessions/{session}', [ParentController::class, 'updateSession'])->name('sessions.update');
    Route::delete('/sessions/{session}', [ParentController::class, 'destroySession'])->name('sessions.destroy');
});
