<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers;

Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, config('app.available_locales', ['tr', 'en']), true)) {
        session(['locale' => $locale]);
    }

    return back();
})->name('locale.switch');

Route::get('/', function () {
    return redirect()->route('products.index');
});

// Eski Breeze /dashboard bağlantıları (route yoktu → 404)
Route::redirect('/dashboard', '/products');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::put('/products/bulk-update', [Controllers\ProductController::class, 'bulk_update'])
        ->name('products.bulk_update');

    Route::get('/products/{product}/variations/{variation}/edit', [Controllers\ProductController::class, 'editVariation'])
        ->name('products.variations.edit');

    Route::resource('/products', Controllers\ProductController::class);

    Route::resource('/campaigns', Controllers\CampaignsController::class);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::get('/cron-refresh', [Controllers\CronCommandController::class, 'refresh_migrations'])
    ->middleware(['throttle:cron', 'cron.secret']);
