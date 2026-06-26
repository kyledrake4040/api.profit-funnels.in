<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AgencyController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AutomationController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FunnelController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\PipelineController;
use App\Http\Controllers\Api\FunnelPageController;
use App\Http\Controllers\Api\GoHighLevelWebhookController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\SiteController;
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
    // Tenancy: agencies (owned by the user) and sub-accounts.
    Route::get('agencies', [AgencyController::class, 'index'])->name('agencies.index');
    Route::post('agencies', [AgencyController::class, 'store'])->name('agencies.store');

    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('accounts/{account}', [AccountController::class, 'show'])
        ->middleware('account.member')
        ->name('accounts.show');

    // CRM contacts — nested under an account, gated by membership. Kept fully
    // nested (not shallow) so the {account} param is always present for the
    // account.member guard to scope and authorize against.
    Route::middleware('account.member')->group(function () {
        Route::get('accounts/{account}/dashboard', [DashboardController::class, 'show'])
            ->name('accounts.dashboard');
        Route::post('accounts/{account}/contacts/{contact}/ai-reply', [ContactController::class, 'draftReply'])
            ->name('contacts.ai-reply');
        Route::apiResource('accounts.contacts', ContactController::class);
        Route::apiResource('accounts.pipelines', PipelineController::class)
            ->only(['index', 'store', 'show', 'destroy']);
        Route::apiResource('accounts.opportunities', OpportunityController::class);
        Route::apiResource('accounts.jobs', JobController::class);
        Route::apiResource('accounts.automations', AutomationController::class);

        // Quotes (with line items) → convert to invoices → get paid.
        Route::post('accounts/{account}/quotes/{quote}/accept', [QuoteController::class, 'accept'])->name('quotes.accept');
        Route::post('accounts/{account}/quotes/{quote}/convert', [QuoteController::class, 'convert'])->name('quotes.convert');
        Route::apiResource('accounts.quotes', QuoteController::class);

        Route::post('accounts/{account}/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
        Route::apiResource('accounts.invoices', InvoiceController::class);

        // Client micro-site (one per account).
        Route::get('accounts/{account}/site', [SiteController::class, 'show'])->name('site.show');
        Route::put('accounts/{account}/site', [SiteController::class, 'upsert'])->name('site.upsert');
    });

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
