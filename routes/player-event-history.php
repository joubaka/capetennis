<?php

use App\Http\Controllers\Frontend\PlayerEventRatingHistoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/my-tennis/players/{player}/event-ratings', [PlayerEventRatingHistoryController::class, 'show'])->whereNumber('player')->name('my.tennis.event-history');
    Route::post('/my-tennis/players/{player}/event-ratings/refresh', [PlayerEventRatingHistoryController::class, 'refresh'])->whereNumber('player')->middleware('throttle:2,1')->name('my.tennis.event-history.refresh');
});
