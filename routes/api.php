<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FunnelController;
use App\Http\Controllers\Api\FunnelPageController;
use App\Http\Controllers\Api\GoHighLevelWebhookController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\QuickBooksWebhookController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Middleware\VerifyFunnelWebhookSecret;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// Auth
Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('register', [AuthController::class, 'register'])->name('auth.register');

    Route::middleware('auth:api')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
    });
});

// Public plan browsing
Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
Route::get('plans/{plan}', [PlanController::class, 'show'])->name('plans.show');

// Funnels, pages, subscriptions (authenticated)
Route::middleware('auth:api')->group(function () {
    Route::apiResource('funnels', FunnelController::class);
    Route::apiResource('funnels.pages', FunnelPageController::class);

    Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
    Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
});

// Stripe webhook — authenticated via Stripe signature (or open in dev).
Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook');

// Attribution webhooks — verified by shared secret (X-Funnel-Token / ?token=).
Route::group([
    'prefix'     => 'funnel/webhooks',
    'middleware' => VerifyFunnelWebhookSecret::class,
], function () {
    Route::post('gohighlevel', GoHighLevelWebhookController::class)
        ->name('funnel.webhooks.gohighlevel');
    Route::post('quickbooks', QuickBooksWebhookController::class)
        ->name('funnel.webhooks.quickbooks');
});
