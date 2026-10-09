<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return redirect()->route('categories.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('categories', CategoryController::class);
    Route::get('categories/{category}/print', [CategoryController::class, 'print'])->name('categories.print');
    Route::get('categories/{category}/articles/quick', [ArticleController::class, 'quick'])->name('categories.articles.quick');
    Route::get('categories/{category}/articles/bulk', [ArticleController::class, 'bulk'])->name('categories.articles.bulk');
    Route::resource('categories.articles', ArticleController::class)->shallow()->except('index');
});

require __DIR__.'/auth.php';
