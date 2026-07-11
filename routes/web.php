<?php

use App\Http\Controllers\EditorController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ShareController;
use Illuminate\Support\Facades\Route;

// Redirect root to public website
Route::get('/', fn () => redirect('https://dropzones.dk', 301));

// Auth
Route::get('/login', [EditorController::class, 'showLogin'])->name('login');
Route::post('/login', [EditorController::class, 'login'])->name('login.submit');
Route::post('/logout', [EditorController::class, 'logout'])->name('logout');

// Protected editor routes
Route::middleware('editor.auth')->group(function () {
    Route::get('/editor', [EditorController::class, 'dashboard'])->name('dashboard');
    Route::get('/editor/new', [EditorController::class, 'newEditor'])->name('editor.new');
    Route::get('/editor/{uuid}', [EditorController::class, 'editExport'])->name('editor.edit');

    Route::get('/uploads/{uuid}/stream', [EditorController::class, 'streamUpload'])->name('uploads.stream');

    Route::post('/upload/chunk', [EditorController::class, 'chunk'])->name('upload.chunk');
    Route::post('/upload/logo', [EditorController::class, 'logo'])->name('upload.logo');
    Route::post('/upload/image', [EditorController::class, 'image'])->name('upload.image');
    Route::post('/upload', [EditorController::class, 'upload'])->name('upload');
    Route::post('/export', [EditorController::class, 'export'])->name('export');
    Route::get('/export/{uuid}/status', [EditorController::class, 'status'])->name('export.status');
    Route::post('/export/{uuid}/send-email', [EditorController::class, 'sendEmail'])->name('export.sendEmail');
    Route::delete('/export/{uuid}', [EditorController::class, 'destroy'])->name('export.destroy');
});

// Uploader portal (shared uploader password — separate from the editor login)
Route::get('/portal/login', [PortalController::class, 'showLogin'])->name('portal.login');
Route::post('/portal/login', [PortalController::class, 'login'])->name('portal.login.submit');
Route::middleware('uploader.auth')->group(function () {
    Route::get('/portal', [PortalController::class, 'show'])->name('portal');
    Route::post('/portal/chunk', [PortalController::class, 'chunk'])->name('portal.chunk');
    Route::post('/portal/submit', [PortalController::class, 'submit'])->name('portal.submit');
});

// Share page (public — no auth required)
Route::get('/share/{uuid}', [ShareController::class, 'show'])->name('share');
Route::get('/share/{uuid}/video', [ShareController::class, 'video'])->name('share.video');
Route::get('/share/{uuid}/download', [ShareController::class, 'download'])->name('share.download');
Route::get('/share/{uuid}/images/download', [ShareController::class, 'imagesDownloadAll'])->name('share.images.downloadAll');
Route::get('/share/{uuid}/image/{index}', [ShareController::class, 'image'])->name('share.image')->whereNumber('index');
Route::get('/share/{uuid}/image/{index}/download', [ShareController::class, 'imageDownload'])->name('share.image.download')->whereNumber('index');

