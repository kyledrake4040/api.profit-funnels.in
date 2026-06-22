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

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// Funnel attribution dashboard (login required — shows leads & revenue).
Route::get('/funnel/dashboard', \App\Http\Controllers\FunnelDashboardController::class)
    ->middleware('auth')
    ->name('funnel.dashboard');
