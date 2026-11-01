<?php

use App\Http\Controllers\Backend\TeamSheetTennisController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:super-user'])->prefix('backend/integrations/teamsheet/tennis')->name('backend.integrations.teamsheet.tennis.')->group(function () {
    Route::get('/', [TeamSheetTennisController::class, 'index'])->name('index');
    Route::post('/sync', [TeamSheetTennisController::class, 'sync'])->middleware('throttle:2,1')->name('sync');
    Route::post('/links', [TeamSheetTennisController::class, 'link'])->name('link');
    Route::delete('/links/{id}', [TeamSheetTennisController::class, 'unlink'])->whereNumber('id')->name('unlink');
    Route::post('/matches/{id}/date', [TeamSheetTennisController::class, 'date'])->whereNumber('id')->name('date');
});
