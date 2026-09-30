<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Dashed\DashedPopups\Http\Controllers\Api\V1\PopupController;

Route::prefix('api/v1')
    ->middleware(['auth:sanctum', 'mobile.site'])
    ->group(function (): void {
        Route::get('popups', [PopupController::class, 'index'])->middleware('ability:popups.read');
        // Vóór de {popup}-wildcard, anders wordt 'options' als id opgevat.
        Route::get('popups/options', [PopupController::class, 'options'])->middleware('ability:popups.read');
        Route::get('popups/{popup}', [PopupController::class, 'show'])->whereNumber('popup')->middleware('ability:popups.read');
        Route::post('popups', [PopupController::class, 'store'])->middleware('ability:popups.write');
        Route::patch('popups/{popup}', [PopupController::class, 'update'])->whereNumber('popup')->middleware('ability:popups.write');
        Route::delete('popups/{popup}', [PopupController::class, 'destroy'])->whereNumber('popup')->middleware('ability:popups.write');
        Route::post('popups/{popup}/duplicate', [PopupController::class, 'duplicate'])->whereNumber('popup')->middleware('ability:popups.write');
        Route::post('popups/{popup}/toggle-active', [PopupController::class, 'toggleActive'])->whereNumber('popup')->middleware('ability:popups.write');
    });
