<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BrandAssetController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SecureFileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/brand-logo-transparent',BrandAssetController::class)->name('brand.logo');

Route::redirect('/', '/dashboard');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/product-photo/{filename}',[SecureFileController::class,'product'])->where('filename','[A-Za-z0-9._-]+')->name('product.photo');
    Route::get('/purchase-proof/{filename}',[SecureFileController::class,'payment'])->where('filename','[A-Za-z0-9._-]+')->name('purchase.proof');
    Route::get('/secure-files/{category}/{filename}',[SecureFileController::class,'show'])->whereIn('category',['product','payment','expense','invoice','quote','logistics'])->where('filename','[A-Za-z0-9._-]+')->name('secure-files.show');
    Route::get('/modules/{module}', [ModuleController::class, 'index'])->name('modules.index');
    Route::get('/modules/{module}/create', [ModuleController::class, 'create'])->name('modules.create');
    Route::post('/modules/{module}', [ModuleController::class, 'store'])->name('modules.store');
    Route::get('/modules/{module}/{id}/edit', [ModuleController::class, 'edit'])->whereNumber('id')->name('modules.edit');
    Route::get('/modules/{module}/{id}/pdf', [ModuleController::class, 'pdf'])->whereIn('module',['devis','factures'])->whereNumber('id')->name('modules.pdf');
    Route::get('/modules/paiements/{id}/recu', [ModuleController::class, 'paymentReceipt'])->whereNumber('id')->name('payments.receipt');
    Route::get('/modules/{module}/{id}', [ModuleController::class, 'show'])->whereNumber('id')->name('modules.show');
    Route::put('/modules/{module}/{id}', [ModuleController::class, 'update'])->whereNumber('id')->name('modules.update');
    Route::delete('/modules/employes/{id}', [ModuleController::class, 'destroyEmployee'])->whereNumber('id')->name('employees.destroy');
    Route::get('/modules/{module}/export', [ModuleController::class, 'export'])->name('modules.export');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
