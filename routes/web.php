<?php

use App\Http\Controllers\MapController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', MapController::class)->name('map');

Route::post('/api/reports', [ReportController::class, 'store'])->middleware('throttle:reports');
