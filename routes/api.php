<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\UpsaleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes here are automatically prefixed with /api by the RouteServiceProvider.
| The billing service exposes health and upsale endpoints.
| Sync operations are triggered via console commands (scheduled / RoadRunner cron).
|
*/

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
*/

Route::get('/health', HealthController::class)->name('health');

/*
| Upsale
|
| Full paths (with /api prefix):
|   POST /api/upsale/v3/add-subscription/ensure
|   POST /api/upsale/v3/add-subscription/commit
|   POST /api/upsale/v3/replace-subscription/ensure
|   POST /api/upsale/v3/replace-subscription/commit
|
| All operation params are query string parameters.
| Optional upsaleRequestContext is in the JSON request body.
*/

Route::prefix('upsale/v3')->name('upsale.')->group(function () {
    Route::prefix('add-subscription')->name('add.')->group(function () {
        Route::post('/ensure', [UpsaleController::class, 'addSubscriptionEnsure'])->name('ensure');
        Route::post('/commit', [UpsaleController::class, 'addSubscriptionCommit'])->name('commit');
    });

    Route::prefix('replace-subscription')->name('replace.')->group(function () {
        Route::post('/ensure', [UpsaleController::class, 'replaceSubscriptionEnsure'])->name('ensure');
        Route::post('/commit', [UpsaleController::class, 'replaceSubscriptionCommit'])->name('commit');
    });
});
