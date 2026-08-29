<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BrandAssetController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\SecureFileController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/brand-logo-transparent',BrandAssetController::class)->name('brand.logo');

Route::get('/',[PublicSiteController::class,'home'])->name('public.home');
Route::get('/catalogue',[PublicSiteController::class,'catalog'])->name('public.catalog');
Route::get('/catalogue/{slug}',[PublicSiteController::class,'product'])->name('public.catalog.product');
Route::get('/catalogue/{slug}/image/{index?}',[PublicSiteController::class,'productImage'])->whereNumber('index')->name('public.catalog.image');
Route::get('/suivi',[PublicSiteController::class,'tracking'])->name('public.tracking');
Route::post('/suivi',[PublicSiteController::class,'lookupTracking'])->middleware('throttle:10,1')->name('public.tracking.lookup');
Route::get('/suivi/securise/{token}',[PublicSiteController::class,'trackingLink'])->where('token','[A-Za-z0-9]{32}')->middleware('throttle:30,1')->name('public.tracking.link');
Route::get('/contact',[PublicSiteController::class,'contact'])->name('public.contact');
Route::post('/contact',[PublicSiteController::class,'submitContact'])->middleware('throttle:5,1')->name('public.contact.submit');
Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified', 'module.access:dashboard'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::middleware('super.admin')->group(function () {
        Route::get('/admin/utilisateurs',[UserManagementController::class,'index'])->name('admin.users.index');
        Route::post('/admin/utilisateurs',[UserManagementController::class,'store'])->name('admin.users.store');
        Route::put('/admin/utilisateurs/{user}',[UserManagementController::class,'update'])->name('admin.users.update');
    });
    Route::get('/product-photo/{filename}',[SecureFileController::class,'product'])->where('filename','[A-Za-z0-9._-]+')->name('product.photo');
    Route::get('/purchase-proof/{filename}',[SecureFileController::class,'payment'])->where('filename','[A-Za-z0-9._-]+')->name('purchase.proof');
    Route::get('/secure-files/{category}/{filename}',[SecureFileController::class,'show'])->whereIn('category',['product','payment','expense','invoice','quote','logistics'])->where('filename','[A-Za-z0-9._-]+')->name('secure-files.show');
    Route::get('/modules/{module}', [ModuleController::class, 'index'])->middleware('module.access')->name('modules.index');
    Route::get('/modules/{module}/create', [ModuleController::class, 'create'])->middleware('module.access')->name('modules.create');
    Route::post('/modules/{module}', [ModuleController::class, 'store'])->middleware('module.access')->name('modules.store');
    Route::get('/modules/{module}/{id}/edit', [ModuleController::class, 'edit'])->middleware('module.access')->whereNumber('id')->name('modules.edit');
    Route::get('/modules/{module}/{id}/pdf', [ModuleController::class, 'pdf'])->middleware('module.access')->whereIn('module',['devis','factures'])->whereNumber('id')->name('modules.pdf');
    Route::get('/modules/paiements/{id}/recu', [ModuleController::class, 'paymentReceipt'])->middleware('module.access:paiements')->whereNumber('id')->name('payments.receipt');
    Route::get('/modules/{module}/{id}', [ModuleController::class, 'show'])->middleware('module.access')->whereNumber('id')->name('modules.show');
    Route::post('/modules/commandes/{id}/tracking/regenerate',[OrderTrackingController::class,'regenerate'])->middleware('module.access:commandes')->whereNumber('id')->name('orders.tracking.regenerate');
    Route::patch('/modules/commandes/{id}/tracking',[OrderTrackingController::class,'toggle'])->middleware('module.access:commandes')->whereNumber('id')->name('orders.tracking.toggle');
    Route::put('/modules/{module}/{id}', [ModuleController::class, 'update'])->middleware('module.access')->whereNumber('id')->name('modules.update');
    Route::delete('/modules/employes/{id}', [ModuleController::class, 'destroyEmployee'])->middleware('module.access:employes')->whereNumber('id')->name('employees.destroy');
    Route::get('/modules/{module}/export', [ModuleController::class, 'export'])->middleware('module.access')->name('modules.export');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
