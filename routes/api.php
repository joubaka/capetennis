<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\Api\V1\JtaIntegrationController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/subscribe', [SubscriberController::class, 'subscribe']);

Route::prefix('v1/integrations/jta')
    ->middleware(['auth:sanctum', 'jta.integration', 'throttle:jta-results'])
    ->name('api.v1.integrations.jta.')
    ->group(function () {
        Route::get('health', [JtaIntegrationController::class, 'health'])->name('health');
        Route::post('players/resolve', [JtaIntegrationController::class, 'resolvePlayer'])->name('players.resolve');
        Route::get('players/{player}/results', [JtaIntegrationController::class, 'playerResults'])
            ->whereNumber('player')
            ->name('players.results');
        Route::get('players/{player}/event-results', [JtaIntegrationController::class, 'playerEventResults'])
            ->whereNumber('player')
            ->name('players.event-results');
        Route::get('players/{player}/series-rankings', [JtaIntegrationController::class, 'playerSeriesRankings'])
            ->whereNumber('player')
            ->name('players.series-rankings');
    });

// ─── Draw canonical API (requires auth) ──────────────────────────────────────
Route::middleware(['web', 'auth'])->prefix('draws/{draw}')->group(function () {
    Route::get('hub',                   [\App\Http\Controllers\Api\DrawApiController::class, 'hub'])
         ->name('api.draws.hub');

    Route::post('fixtures/{fixture}/score',   [\App\Http\Controllers\Api\DrawApiController::class, 'storeScore'])
         ->name('api.draws.fixtures.score.store');

    Route::delete('fixtures/{fixture}/score', [\App\Http\Controllers\Api\DrawApiController::class, 'deleteScore'])
         ->name('api.draws.fixtures.score.delete');

    Route::post('groups',    [\App\Http\Controllers\Api\DrawApiController::class, 'saveGroups'])
         ->name('api.draws.groups.save');

    Route::post('schedule',  [\App\Http\Controllers\Api\DrawApiController::class, 'saveSchedule'])
         ->name('api.draws.schedule.save');

    Route::get('schedule/summary', [\App\Http\Controllers\Api\DrawApiController::class, 'scheduleSummary'])
         ->name('api.draws.schedule.summary');

    // ── Bracket canonical API ─────────────────────────────────────────
    Route::get('brackets',                    [\App\Http\Controllers\Api\DrawApiController::class, 'brackets'])
         ->name('api.draws.brackets');

    Route::post('brackets/generate',          [\App\Http\Controllers\Api\DrawApiController::class, 'generateBrackets'])
         ->name('api.draws.brackets.generate');
});
