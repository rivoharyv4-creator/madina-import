<?php

use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\ManualPaymentAdminController;
use App\Http\Middleware\RedirectCustomerAccount;
use Illuminate\Support\Facades\Route;

Route::get('/panier', [CustomerOrderController::class, 'cart'])->name('customer.cart');
Route::middleware(RedirectCustomerAccount::class)->group(function () {
    Route::get('/connexion', [CustomerAuthController::class, 'form'])->name('customer.login');
    Route::post('/connexion', [CustomerAuthController::class, 'login'])->middleware('throttle:customer-auth');
    Route::get('/inscription', [CustomerAuthController::class, 'form'])->name('customer.register');
    Route::post('/inscription', [CustomerAuthController::class, 'register'])->middleware('throttle:customer-auth');
});
Route::middleware(['auth', 'customer.account', 'verified', 'throttle:60,1'])->group(function () {
    Route::get('/commande', [CustomerOrderController::class, 'checkout'])->name('customer.checkout');
    Route::post('/commande/apercu', [CustomerOrderController::class, 'preview'])->name('customer.checkout.preview');
    Route::post('/commande', [CustomerOrderController::class, 'store'])->name('customer.checkout.store');
    Route::get('/mes-commandes', [CustomerOrderController::class, 'index'])->name('customer.orders.index');
    Route::get('/mes-commandes/{number}', [CustomerOrderController::class, 'show'])->name('customer.orders.show');
    Route::get('/mes-commandes/{number}/articles/{item}/image', [CustomerOrderController::class, 'itemImage'])->whereNumber('item')->name('customer.orders.image');
    Route::post('/mes-commandes/{number}/paiement', [CustomerOrderController::class, 'submit'])->name('customer.orders.payment');
});
Route::middleware(['auth', 'verified', 'throttle:60,1'])->group(function () {
    Route::get('/paiement-preuves/{id}', [CustomerOrderController::class, 'proof'])->whereNumber('id')->name('customer.proof');
});
Route::middleware(['auth', 'backoffice.account', 'verified', 'throttle:60,1'])->group(function () {
    Route::get('/admin/paiements-manuels', [ManualPaymentAdminController::class, 'index'])->name('admin.manual-payments');
    Route::post('/admin/comptes-paiement', [ManualPaymentAdminController::class, 'account']);
    Route::put('/admin/comptes-paiement/{id}', [ManualPaymentAdminController::class, 'account'])->whereNumber('id');
    Route::delete('/admin/comptes-paiement/{id}', [ManualPaymentAdminController::class, 'deleteAccount'])->whereNumber('id');
    Route::post('/admin/paiements-manuels/{id}', [ManualPaymentAdminController::class, 'review'])->whereNumber('id');
    Route::patch('/admin/commandes-web/{id}', [ManualPaymentAdminController::class, 'orderStatus'])->whereNumber('id');
});
