<?php

use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\ContentPageController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\VisitorAnalyticsController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('store.home');
Route::get('/products', [ProductController::class, 'index'])->name('store.products.index');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('store.products.show');
Route::get('/page/{slug}', ContentPageController::class)->name('store.pages.show');
Route::get('/cart', [CartController::class, 'index'])->name('store.cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('store.cart.store');
Route::patch('/cart/{line}', [CartController::class, 'update'])->name('store.cart.update');
Route::delete('/cart/{line}', [CartController::class, 'destroy'])->name('store.cart.destroy');
Route::post('/analytics/events', VisitorAnalyticsController::class)
    ->middleware('throttle:120,1')
    ->name('visitor.analytics.track');
