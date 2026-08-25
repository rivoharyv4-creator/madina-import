<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/brand-logo-transparent', function () {
    return response()->file(public_path('brand/madina-import-logo-transparent.png'), [
        'Content-Type' => 'image/png',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('brand.logo');

Route::get('/product-photo/{filename}', function (string $filename) {
    $path = storage_path('app/public/product-photos/'.basename($filename));
    abort_unless(is_file($path), 404);

    return response()->file($path, ['Cache-Control' => 'public, max-age=86400']);
})->where('filename', '[A-Za-z0-9._-]+')->name('product.photo');

Route::redirect('/', '/dashboard');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/purchase-proof/{filename}', function (string $filename) {
        $path=storage_path('app/public/purchase-proofs/'.basename($filename));
        abort_unless(is_file($path),404);
        return response()->file($path,['Cache-Control'=>'private, max-age=3600']);
    })->where('filename','[A-Za-z0-9._-]+')->name('purchase.proof');
    Route::get('/modules/{module}', [ModuleController::class, 'index'])->name('modules.index');
    Route::get('/modules/{module}/create', [ModuleController::class, 'create'])->name('modules.create');
    Route::post('/modules/{module}', [ModuleController::class, 'store'])->name('modules.store');
    Route::get('/modules/{module}/{id}/edit', [ModuleController::class, 'edit'])->whereNumber('id')->name('modules.edit');
    Route::get('/modules/{module}/{id}', [ModuleController::class, 'show'])->whereNumber('id')->name('modules.show');
    Route::put('/modules/{module}/{id}', [ModuleController::class, 'update'])->whereNumber('id')->name('modules.update');
    Route::delete('/modules/employes/{id}', [ModuleController::class, 'destroyEmployee'])->whereNumber('id')->name('employees.destroy');
    Route::get('/modules/{module}/export', [ModuleController::class, 'export'])->name('modules.export');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
