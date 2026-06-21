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

// Stripe webhook — public endpoint, authenticated via signature verification.
Route::post('stripe/webhook', [\App\Http\Controllers\Api\StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook');

// Funnel attribution webhooks — verified by a shared secret (X-Funnel-Token
// header or ?token=), enforced when FUNNEL_WEBHOOK_SECRET is configured.
Route::group([
    'prefix' => 'funnel/webhooks',
    'middleware' => \App\Http\Middleware\VerifyFunnelWebhookSecret::class,
], function () {
    Route::post('gohighlevel', \App\Http\Controllers\Api\GoHighLevelWebhookController::class)
        ->name('funnel.webhooks.gohighlevel');
    Route::post('quickbooks', \App\Http\Controllers\Api\QuickBooksWebhookController::class)
        ->name('funnel.webhooks.quickbooks');
});
