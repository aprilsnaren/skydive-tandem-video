<?php

use App\Http\Controllers\EditorController;
use App\Http\Controllers\ShareController;
use Illuminate\Support\Facades\Route;

// Editor
Route::get('/', [EditorController::class, 'index'])->name('editor');
Route::post('/upload/chunk', [EditorController::class, 'chunk'])->name('upload.chunk');
Route::post('/upload/logo', [EditorController::class, 'logo'])->name('upload.logo');
Route::post('/upload', [EditorController::class, 'upload'])->name('upload');
Route::post('/export', [EditorController::class, 'export'])->name('export');
Route::get('/export/{uuid}/status', [EditorController::class, 'status'])->name('export.status');
Route::post('/export/{uuid}/send-email', [EditorController::class, 'sendEmail'])->name('export.sendEmail');

// Share page (public)
Route::get('/share/{uuid}', [ShareController::class, 'show'])->name('share');
Route::get('/share/{uuid}/video', [ShareController::class, 'video'])->name('share.video');
Route::get('/share/{uuid}/download', [ShareController::class, 'download'])->name('share.download');
