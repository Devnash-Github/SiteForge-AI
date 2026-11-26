<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FactController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\ResearchController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\WebsiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

// Public websites and their images (no authentication).
Route::get('/sites/{slug}', [PublicSiteController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('sites.show');
Route::get('/media/{uuid}', [MediaController::class, 'show'])->whereUuid('uuid')->name('media.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/websites', [WebsiteController::class, 'index'])->name('websites.index');
    Route::get('/websites/create', [BusinessController::class, 'create'])->name('websites.create');
    Route::get('/websites/{website}/studio', [WebsiteController::class, 'studio'])->name('websites.studio');
    Route::get('/websites/{website}/preview', [WebsiteController::class, 'preview'])->name('websites.preview');
    Route::post('/websites/{website}/generate', [WebsiteController::class, 'generate'])->middleware('throttle:ai')->name('websites.generate');
    Route::patch('/websites/{website}', [WebsiteController::class, 'update'])->name('websites.update');
    Route::delete('/websites/{website}', [WebsiteController::class, 'destroy'])->name('websites.destroy');

    // Studio JSON endpoints
    Route::scopeBindings()->prefix('/websites/{website}')->name('websites.')->group(function () {
        Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::post('/sections/reorder', [SectionController::class, 'reorder'])->name('sections.reorder');
        Route::put('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
        Route::post('/sections/{section}/regenerate', [SectionController::class, 'regenerate'])->middleware('throttle:ai')->name('sections.regenerate');
        Route::put('/theme', [SectionController::class, 'updateTheme'])->name('theme.update');
        Route::post('/publish', [PublishController::class, 'publish'])->name('publish');
        Route::post('/unpublish', [PublishController::class, 'unpublish'])->name('unpublish');
        Route::get('/slug-check', [PublishController::class, 'checkSlug'])->name('slug-check');
    });

    Route::get('/businesses', [BusinessController::class, 'index'])->name('businesses.index');
    Route::post('/businesses', [BusinessController::class, 'store'])->middleware('throttle:uploads')->name('businesses.store');
    Route::get('/businesses/{business}', [BusinessController::class, 'show'])->name('businesses.show');
    Route::put('/businesses/{business}', [BusinessController::class, 'update'])->name('businesses.update');
    Route::delete('/businesses/{business}', [BusinessController::class, 'destroy'])->name('businesses.destroy');

    Route::scopeBindings()->prefix('/businesses/{business}')->name('businesses.')->group(function () {
        Route::get('/research', [ResearchController::class, 'show'])->name('research');
        Route::post('/research', [ResearchController::class, 'rerun'])->name('research.rerun');
        Route::post('/websites', [WebsiteController::class, 'store'])->name('websites.store');

        Route::post('/sources', [SourceController::class, 'store'])->middleware('throttle:uploads')->name('sources.store');
        Route::post('/sources/{source}/retry', [SourceController::class, 'retry'])->name('sources.retry');
        Route::post('/sources/{source}/identity', [SourceController::class, 'identity'])->name('sources.identity');
        Route::delete('/sources/{source}', [SourceController::class, 'destroy'])->name('sources.destroy');

        Route::post('/facts', [FactController::class, 'store'])->name('facts.store');
        Route::post('/facts/confirm-all', [FactController::class, 'confirmAll'])->name('facts.confirm-all');
        Route::post('/conflicts/resolve', [FactController::class, 'resolve'])->name('conflicts.resolve');
        Route::patch('/facts/{fact}', [FactController::class, 'update'])->name('facts.update');
        Route::delete('/facts/{fact}', [FactController::class, 'destroy'])->name('facts.destroy');

        Route::post('/media', [MediaController::class, 'store'])->middleware('throttle:uploads')->name('media.store');
        Route::patch('/media/{media}', [MediaController::class, 'update'])->name('media.update');
        Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::delete('/settings/account', [SettingsController::class, 'destroyAccount'])->name('account.destroy');
});
