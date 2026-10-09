<?php

use App\Http\Controllers\ArticleAssistController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\BatchArticleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PendingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicCategoryController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::get('p/alle/{token}', [PublicCategoryController::class, 'overview'])->name('public.overview');
Route::get('p/{category:public_token}', [PublicCategoryController::class, 'show'])->name('public.category');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('categories', CategoryController::class);
    Route::get('categories/{category}/print', [CategoryController::class, 'print'])->name('categories.print');
    Route::get('categories/{category}/collage', [CategoryController::class, 'collage'])->name('categories.collage');
    Route::post('categories/{category}/public-link', [CategoryController::class, 'regeneratePublicLink'])->name('categories.public-link');
    Route::get('categories/{category}/articles/quick', [ArticleController::class, 'quick'])->name('categories.articles.quick');
    Route::get('categories/{category}/articles/bulk', [ArticleController::class, 'bulk'])->name('categories.articles.bulk');
    Route::get('articles', [ArticleController::class, 'index'])->name('articles.index');
    Route::get('articles/batch', [BatchArticleController::class, 'edit'])->name('articles.batch.edit');
    Route::post('articles/batch', [BatchArticleController::class, 'update'])->name('articles.batch.update');
    Route::post('articles/ai-suggest', ArticleAssistController::class)->middleware('throttle:30,1')->name('articles.ai-suggest');
    Route::post('articles/public-link', [ArticleController::class, 'regeneratePublicLink'])->name('articles.public-link');
    Route::resource('categories.articles', ArticleController::class)->shallow()->except('index');
    Route::patch('articles/{article}/sold', [ArticleController::class, 'sold'])->name('articles.sold');
    Route::patch('articles/{article}/mark/{flag}', [PendingController::class, 'mark'])->whereIn('flag', ['paid', 'shipped', 'picked_up'])->name('articles.mark');
    Route::patch('articles/{article}/unmark/{flag}', [PendingController::class, 'unmark'])->whereIn('flag', ['paid', 'shipped', 'picked_up'])->name('articles.unmark');
    Route::get('offen/{type}', [PendingController::class, 'index'])->whereIn('type', array_keys(PendingController::TYPES))->name('pending.index');
});

require __DIR__.'/auth.php';
