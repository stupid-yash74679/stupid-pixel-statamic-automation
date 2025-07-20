<?php

use Illuminate\Support\Facades\Route;
use StupidPixel\StatamicAutomation\Http\Controllers\StatamicAutoBloggerController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

Route::prefix('api/autoblogger')->middleware(['web', VerifyCsrfToken::class])->group(function () {
    // Entries CRUD
    Route::get('/entries', [StatamicAutoBloggerController::class, 'listEntries']);
    Route::post('/entries', [StatamicAutoBloggerController::class, 'storeEntry']);
    Route::post('/entries/bulk', [StatamicAutoBloggerController::class, 'bulkStoreEntries']);
    Route::get('/entries/{slug}', [StatamicAutoBloggerController::class, 'showEntry']);
    Route::put('/entries/{slug}', [StatamicAutoBloggerController::class, 'updateEntry']);
    Route::delete('/entries/{slug}', [StatamicAutoBloggerController::class, 'deleteEntry']);

    // Collections CRUD
    Route::get('/collections', [StatamicAutoBloggerController::class, 'listCollections']);
    Route::post('/collections', [StatamicAutoBloggerController::class, 'storeCollection']);
    Route::get('/collections/{handle}', [StatamicAutoBloggerController::class, 'showCollection']);
    Route::put('/collections/{handle}', [StatamicAutoBloggerController::class, 'updateCollection']);
    Route::delete('/collections/{handle}', [StatamicAutoBloggerController::class, 'deleteCollection']);

    // Blueprints CRUD
    Route::get('/blueprints', [StatamicAutoBloggerController::class, 'listBlueprints']);
    Route::post('/blueprints', [StatamicAutoBloggerController::class, 'storeBlueprint']);
    Route::get('/blueprints/{handle}', [StatamicAutoBloggerController::class, 'showBlueprint']);
    Route::put('/blueprints/{handle}', [StatamicAutoBloggerController::class, 'updateBlueprint']);
    Route::delete('/blueprints/{handle}', [StatamicAutoBloggerController::class, 'deleteBlueprint']);

    // Assets CRUD
    Route::get('/assets', [StatamicAutoBloggerController::class, 'listAssets']);
    Route::post('/assets', [StatamicAutoBloggerController::class, 'storeAsset']);
    Route::post('/assets/from-url', [StatamicAutoBloggerController::class, 'storeAssetFromUrl']);
    Route::get('/assets/{container}/{path}', [StatamicAutoBloggerController::class, 'showAsset'])->where('path', '.*');
    Route::put('/assets/{container}/{path}', [StatamicAutoBloggerController::class, 'updateAsset'])->where('path', '.*');
    Route::delete('/assets/{container}/{path}', [StatamicAutoBloggerController::class, 'deleteAsset'])->where('path', '.*');
});