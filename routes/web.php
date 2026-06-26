<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Public sales site for the attribution service.
Route::get('/', [\App\Http\Controllers\LandingController::class, 'show'])->name('landing');
Route::post('/leads', [\App\Http\Controllers\LandingController::class, 'capture'])
    ->middleware('throttle:10,1')
    ->name('leads.capture');

// Paid signup — pricing buttons start a Stripe subscription checkout.
Route::get('/checkout-success', [\App\Http\Controllers\CheckoutController::class, 'success'])
    ->name('checkout.success');
Route::get('/checkout/{plan}', [\App\Http\Controllers\CheckoutController::class, 'start'])
    ->name('checkout.start');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// Funnel attribution dashboard (login required — shows leads & revenue).
Route::get('/funnel/dashboard', \App\Http\Controllers\FunnelDashboardController::class)
    ->middleware('auth')
    ->name('funnel.dashboard');

// CRM console — a zero-build SPA that talks to the token-auth API. Auth happens
// client-side via /api/auth/login, so the page itself is public; the data
// behind it is not.
Route::view('/app', 'app.console')->name('app.console');

// Public invoice payment: a business shares /pay/{token}; the client opens it,
// sees what they owe, and pays online via Stripe Checkout. No login required.
Route::get('/pay/{token}', [\App\Http\Controllers\InvoicePaymentController::class, 'show'])->name('pay.show');
Route::get('/pay/{token}/checkout', [\App\Http\Controllers\InvoicePaymentController::class, 'checkout'])
    ->middleware('throttle:20,1')
    ->name('pay.checkout');
Route::get('/pay/{token}/success', [\App\Http\Controllers\InvoicePaymentController::class, 'success'])->name('pay.success');

// Public client micro-sites: a published business site + lead form that drops
// enquiries into that account's CRM. Lead posts are rate-limited.
Route::get('/s/{slug}', [\App\Http\Controllers\SitePublicController::class, 'show'])->name('site.public');
Route::post('/s/{slug}/lead', [\App\Http\Controllers\SitePublicController::class, 'lead'])
    ->middleware('throttle:10,1')
    ->name('site.public.lead');
