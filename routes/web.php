<?php

use App\Http\Controllers\BaseController;
use App\Http\Controllers\SyncController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::any('/', [BaseController::class, 'index'])->withoutMiddleware(VerifyCsrfToken::class)->name('index');
Route::any('/settings', [BaseController::class, 'settings'])->withoutMiddleware(VerifyCsrfToken::class)->name('settings');
Route::any('/install', [BaseController::class, 'install'])->withoutMiddleware(VerifyCsrfToken::class)->name('install');
Route::post('/import', [SyncController::class, 'importProcess'])->withoutMiddleware(VerifyCsrfToken::class)->name('import.process');
Route::any('/export', [SyncController::class, 'exportProcess'])->withoutMiddleware(VerifyCsrfToken::class)->name('export.process');
Route::any('/event/handler', [BaseController::class, 'eventHandler'])->withoutMiddleware(VerifyCsrfToken::class)->name('eventHandler');
Route::post('/feedback', [BaseController::class, 'feedback'])->withoutMiddleware(VerifyCsrfToken::class)->name('feedback');
