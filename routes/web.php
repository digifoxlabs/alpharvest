<?php

use App\Http\Controllers\Web\CheckoutController;
use App\Http\Controllers\Web\AdminCategoryController;
use App\Http\Controllers\Web\AdminDashboardController;
use App\Http\Controllers\Web\AdminMessageController;
use App\Http\Controllers\Web\AdminProductController;
use App\Http\Controllers\Web\AdminStoreController;
use App\Http\Controllers\Web\AdminTenantController;
use App\Http\Controllers\Web\PlatformController;
use App\Http\Controllers\Web\FeedController;
use App\Http\Controllers\Web\TenantCategoryController;
use App\Http\Controllers\Web\TenantOrderController;
use App\Http\Controllers\Web\TenantProductController;
use App\Http\Controllers\Web\TenantStoreController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PlatformController::class, 'home'])->name('platform.home');
Route::get('/dashboard/{tenant:slug}', [PlatformController::class, 'dashboard'])->name('dashboard.show');
Route::get('/dashboard/{tenant:slug}/inbox', [PlatformController::class, 'inbox'])->name('dashboard.inbox');
Route::get('/dashboard/{tenant:slug}/orders', [PlatformController::class, 'orders'])->name('dashboard.orders');
Route::get('/dashboard/{tenant:slug}/stores', [TenantStoreController::class, 'index'])->name('dashboard.stores.index');
Route::get('/dashboard/{tenant:slug}/stores/create', [TenantStoreController::class, 'create'])->name('dashboard.stores.create');
Route::post('/dashboard/{tenant:slug}/stores', [TenantStoreController::class, 'store'])->name('dashboard.stores.store');
Route::get('/dashboard/{tenant:slug}/stores/{store:slug}/edit', [TenantStoreController::class, 'edit'])->name('dashboard.stores.edit');
Route::put('/dashboard/{tenant:slug}/stores/{store:slug}', [TenantStoreController::class, 'update'])->name('dashboard.stores.update');
Route::delete('/dashboard/{tenant:slug}/stores/{store:slug}', [TenantStoreController::class, 'destroy'])->name('dashboard.stores.destroy');
Route::get('/dashboard/{tenant:slug}/categories', [TenantCategoryController::class, 'index'])->name('dashboard.categories.index');
Route::post('/dashboard/{tenant:slug}/categories', [TenantCategoryController::class, 'store'])->name('dashboard.categories.store');
Route::get('/dashboard/{tenant:slug}/categories/{category}/edit', [TenantCategoryController::class, 'edit'])->name('dashboard.categories.edit');
Route::put('/dashboard/{tenant:slug}/categories/{category}', [TenantCategoryController::class, 'update'])->name('dashboard.categories.update');
Route::delete('/dashboard/{tenant:slug}/categories/{category}', [TenantCategoryController::class, 'destroy'])->name('dashboard.categories.destroy');
Route::get('/dashboard/{tenant:slug}/products', [TenantProductController::class, 'index'])->name('dashboard.products.index');
Route::get('/dashboard/{tenant:slug}/products/create', [TenantProductController::class, 'create'])->name('dashboard.products.create');
Route::post('/dashboard/{tenant:slug}/products', [TenantProductController::class, 'store'])->name('dashboard.products.store');
Route::get('/dashboard/{tenant:slug}/products/{product}/edit', [TenantProductController::class, 'edit'])->name('dashboard.products.edit');
Route::put('/dashboard/{tenant:slug}/products/{product}', [TenantProductController::class, 'update'])->name('dashboard.products.update');
Route::delete('/dashboard/{tenant:slug}/products/{product}', [TenantProductController::class, 'destroy'])->name('dashboard.products.destroy');
Route::post('/dashboard/{tenant:slug}/orders/{order}/request-address', [TenantOrderController::class, 'requestAddress'])->name('dashboard.orders.request-address');
Route::post('/dashboard/{tenant:slug}/orders/{order}/send-payment-link', [TenantOrderController::class, 'sendPaymentLink'])->name('dashboard.orders.send-payment-link');
Route::post('/dashboard/{tenant:slug}/orders/{order}/status', [TenantOrderController::class, 'updateStatus'])->name('dashboard.orders.update-status');
Route::get('/stores/{store:slug}/catalog', [PlatformController::class, 'catalog'])->name('platform.catalog');
Route::get('/stores/{store:slug}/products/{product}', [PlatformController::class, 'product'])->name('platform.products.show');
Route::get('/feeds/meta-products', [FeedController::class, 'metaProducts'])->name('feeds.meta-products');
Route::get('/feeds/meta-placeholder.svg', [FeedController::class, 'metaPlaceholder'])->name('feeds.meta-placeholder');
Route::get('/pay/{payment:reference}', [CheckoutController::class, 'show'])->name('payments.show');
Route::post('/pay/{payment:reference}/confirm', [CheckoutController::class, 'confirm'])->name('payments.confirm');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/tenants', [AdminTenantController::class, 'index'])->name('tenants.index');
    Route::post('/tenants', [AdminTenantController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{tenant:slug}/edit', [AdminTenantController::class, 'edit'])->name('tenants.edit');
    Route::put('/tenants/{tenant:slug}', [AdminTenantController::class, 'update'])->name('tenants.update');
    Route::delete('/tenants/{tenant:slug}', [AdminTenantController::class, 'destroy'])->name('tenants.destroy');

    Route::get('/stores', [AdminStoreController::class, 'index'])->name('stores.index');
    Route::get('/stores/create', [AdminStoreController::class, 'create'])->name('stores.create');
    Route::post('/stores', [AdminStoreController::class, 'store'])->name('stores.store');
    Route::get('/stores/{store:slug}/edit', [AdminStoreController::class, 'edit'])->name('stores.edit');
    Route::put('/stores/{store:slug}', [AdminStoreController::class, 'update'])->name('stores.update');
    Route::delete('/stores/{store:slug}', [AdminStoreController::class, 'destroy'])->name('stores.destroy');

    Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/messages', [AdminMessageController::class, 'index'])->name('messages.index');

    Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
    Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
});

