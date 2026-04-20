<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'))
    ->middleware('auth')
    ->name('home');

Route::middleware('auth')->group(function () {
    Route::post('campaigns/preview-audience', [CampaignController::class, 'previewAudience'])
        ->name('campaigns.preview-audience');
    Route::post('campaigns/{campaign}/schedule', [CampaignController::class, 'schedule'])
        ->name('campaigns.schedule');

    Route::resource('campaigns', CampaignController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);

    Route::resource('contacts', ContactController::class)
        ->only(['index', 'create', 'store', 'edit', 'update']);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
