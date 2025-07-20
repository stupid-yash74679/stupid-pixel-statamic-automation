<?php

use Illuminate\Support\Facades\Route;
use StupidPixel\StatamicAutomation\Http\Controllers\StatamicAutoBloggerController;
use StupidPixel\StatamicAutomation\Http\Controllers\NavigateAiController;
use StupidPixel\StatamicAutomation\Http\Controllers\AiServerController;
use StupidPixel\StatamicAutomation\Http\Controllers\NavigationController;
use StupidPixel\StatamicAutomation\Http\Controllers\ProductServiceController;
use StupidPixel\StatamicAutomation\Http\Controllers\BlogController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

Route::prefix('api/autoblogger')->middleware(['web', VerifyCsrfToken::class])->group(function () {
    Route::get('/csrf-token', function () {
        return response()
            ->json(['token' => csrf_token()])
            ->withCookie(cookie('XSRF-TOKEN', csrf_token(), 60, '/', null, false, false));
    });

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

Route::prefix('api/navigate-ai')->middleware(['web', VerifyCsrfToken::class])->group(function () {
    Route::post('/generate-pages', [NavigateAiController::class, 'generatePages']);
});

Route::prefix('api/navigation')->middleware(['web', VerifyCsrfToken::class])->group(function () {
    Route::post('/update', [NavigationController::class, 'updateNavigation']);
});

Route::prefix('api/products-services')->middleware(['web', VerifyCsrfToken::class])->group(function () {
    Route::post('/create', [ProductServiceController::class, 'createProduct']);
    Route::put('/update/{id}', [ProductServiceController::class, 'updateProduct']);
    Route::delete('/delete/{id}', [ProductServiceController::class, 'deleteProduct']);
});

Route::prefix('api/blogs')->middleware(['web', VerifyCsrfToken::class])->group(function () {
    Route::post('/create', [BlogController::class, 'createBlog']);
    Route::put('/update/{id}', [BlogController::class, 'updateBlog']);
    Route::delete('/delete/{id}', [BlogController::class, 'deleteBlog']);
});