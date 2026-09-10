<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SchemaLens\Http\Controllers\SchemaLensController;

Route::get('/', [SchemaLensController::class, 'index'])->name('schemalens.dashboard');
Route::post('/compare', [SchemaLensController::class, 'compare'])->name('schemalens.compare');
Route::get('/export/{format}', [SchemaLensController::class, 'export'])->name('schemalens.export');
Route::post('/migration', [SchemaLensController::class, 'migration'])->name('schemalens.migration');
