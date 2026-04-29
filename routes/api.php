<?php

use App\Http\Controllers\Api\FinancialsController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\RosterController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::post('/roster/analyze', [RosterController::class, 'analyze']);
Route::get('/financials/{ticker}', [FinancialsController::class, 'show']);
