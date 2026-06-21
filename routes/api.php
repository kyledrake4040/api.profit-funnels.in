<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'auth'], function () {
    Route::post("login", [\App\Http\Controllers\Api\AuthController::class, 'login'])->name('auth.login');
});

// Funnel attribution webhooks (no auth: verified by the provider's shared secret).
Route::group(['prefix' => 'funnel/webhooks'], function () {
    Route::post('gohighlevel', \App\Http\Controllers\Api\GoHighLevelWebhookController::class)
        ->name('funnel.webhooks.gohighlevel');
    Route::post('quickbooks', \App\Http\Controllers\Api\QuickBooksWebhookController::class)
        ->name('funnel.webhooks.quickbooks');
});
