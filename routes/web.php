<?php

use App\Http\Controllers\BaseController;
use App\Http\Controllers\ImportController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::any('/', [BaseController::class, 'index'])->withoutMiddleware(VerifyCsrfToken::class)->name('index');
Route::any('/install', [BaseController::class, 'install'])->withoutMiddleware(VerifyCsrfToken::class)->name('install');
Route::post('/import', [ImportController::class, 'process'])->withoutMiddleware(VerifyCsrfToken::class)->name('import.process');
Route::any('/event/handler', [BaseController::class, 'eventHandler'])->withoutMiddleware(VerifyCsrfToken::class)->name('eventHandler');
