<?php

use App\Http\Controllers\Backend\TeamSheetTennisController;
use App\Http\Controllers\Frontend\TeamSheetProfileLinkController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:super-user'])->prefix('backend/integrations/teamsheet/tennis')->name('backend.integrations.teamsheet.tennis.')->group(function () {
    Route::get('/', [TeamSheetTennisController::class, 'index'])->name('index');
    Route::post('/sync', [TeamSheetTennisController::class, 'sync'])->middleware('throttle:2,1')->name('sync');
    Route::post('/links', [TeamSheetTennisController::class, 'link'])->name('link');
    Route::delete('/links/{id}', [TeamSheetTennisController::class, 'unlink'])->whereNumber('id')->name('unlink');
    Route::post('/matches/{id}/date', [TeamSheetTennisController::class, 'date'])->whereNumber('id')->name('date');
});

Route::middleware('auth')->name('my.tennis.teamsheet.')->group(function () {
    Route::post('/my-tennis/players/{player}/teamsheet', [TeamSheetProfileLinkController::class, 'start'])->whereNumber('player')->middleware('throttle:5,1')->name('start');
    Route::get('/my-tennis/teamsheet/callback', [TeamSheetProfileLinkController::class, 'callback'])->middleware('throttle:20,1')->name('callback');
    Route::delete('/my-tennis/players/{player}/teamsheet', [TeamSheetProfileLinkController::class, 'unlink'])->whereNumber('player')->name('unlink');
});
