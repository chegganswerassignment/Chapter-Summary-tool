<?php

use App\Http\Controllers\Admin\AiModelController;
use App\Http\Controllers\Admin\AiProviderController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentExportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DocumentController::class, 'index'])->name('dashboard');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/export', DocumentExportController::class)->name('documents.export');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/ai-routing', [AiProviderController::class, 'index'])->name('ai-routing');
    Route::post('/ai-providers', [AiProviderController::class, 'store'])->name('ai-providers.store');
    Route::put('/ai-providers/{ai_provider}', [AiProviderController::class, 'update'])->name('ai-providers.update');
    Route::delete('/ai-providers/{ai_provider}', [AiProviderController::class, 'destroy'])->name('ai-providers.destroy');

    Route::put('/ai-models/active', [AiModelController::class, 'activate'])->name('ai-models.activate');
    Route::post('/ai-models', [AiModelController::class, 'store'])->name('ai-models.store');
    Route::put('/ai-models/{ai_model}', [AiModelController::class, 'update'])->name('ai-models.update');
    Route::delete('/ai-models/{ai_model}', [AiModelController::class, 'destroy'])->name('ai-models.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
