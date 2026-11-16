<?php

use Illuminate\Support\Facades\Route;
use TornadoEngine\AccessGoogle\Http\RulesController;

Route::middleware(['web', 'auth', 'can:access.manage'])
    ->prefix('access/google')
    ->name('access.google.')
    ->group(function () {
        Route::get('/', [RulesController::class, 'index'])->name('index');
        Route::post('/rules', [RulesController::class, 'store'])->name('rules.store');
        Route::delete('/rules/{rule}', [RulesController::class, 'destroy'])->name('rules.destroy');
        Route::post('/sync', [RulesController::class, 'sync'])->name('sync');
    });
