<?php

use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\ContentPageController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\LandingCheckoutController;
use App\Http\Controllers\Storefront\LandingPageController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\VisitorAnalyticsController;
use App\Http\Controllers\Storefront\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('store.home');
Route::get('/shop', [ProductController::class, 'index'])->name('store.shop.index');
Route::get('/products', [ProductController::class, 'index'])->name('store.products.index');
Route::get('/product-category/{category:slug}/{categoryPath?}', [ProductController::class, 'category'])
    ->where('categoryPath', '.*')
    ->name('store.categories.show');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('store.products.show');
Route::middleware('guest')->group(function (): void {
    Route::get('/account/login', [AccountController::class, 'login'])->name('store.account.login');
    Route::post('/account/login', [AccountController::class, 'authenticate'])->middleware('throttle:10,1')->name('store.account.authenticate');
    Route::get('/account/register', [AccountController::class, 'register'])->name('store.account.register');
    Route::post('/account/register', [AccountController::class, 'store'])->middleware('throttle:5,1')->name('store.account.store');
});
Route::middleware('auth')->group(function (): void {
    Route::get('/account', [AccountController::class, 'dashboard'])->name('store.account.dashboard');
    Route::post('/account/logout', [AccountController::class, 'logout'])->name('store.account.logout');
});
Route::get('/wishlist', [WishlistController::class, 'index'])->name('store.wishlist.index');
Route::post('/wishlist/{product}', [WishlistController::class, 'store'])->name('store.wishlist.store');
Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->name('store.wishlist.destroy');
Route::get('/contact-us', ContentPageController::class)->defaults('slug', 'contact-us')->name('store.contact');
Route::get('/about-us', ContentPageController::class)->defaults('slug', 'about-us')->name('store.about');
Route::get('/blog', ContentPageController::class)->defaults('slug', 'blog')->name('store.blog');
Route::get('/page/{slug}', ContentPageController::class)->name('store.pages.show');
Route::get('/offer/{landingPage:slug}/preview', [LandingPageController::class, 'preview'])
    ->middleware('signed')
    ->name('store.landing.preview');
Route::get('/offer/{landingPage:slug}', [LandingPageController::class, 'show'])->name('store.landing.show');
Route::post('/offer/{landingPage:slug}/quote', [LandingCheckoutController::class, 'quote'])
    ->middleware('throttle:60,1')->name('store.landing.quote');
Route::post('/offer/{landingPage:slug}/order', [LandingCheckoutController::class, 'store'])
    ->middleware('throttle:10,1')->block(30, 30)->name('store.landing.order');
Route::get('/cart', [CartController::class, 'index'])->name('store.cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('store.cart.store');
Route::patch('/cart/{line}', [CartController::class, 'update'])->name('store.cart.update');
Route::delete('/cart/{line}', [CartController::class, 'destroy'])->name('store.cart.destroy');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('store.checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('store.checkout.store');
Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])
    ->middleware('signed')
    ->name('store.checkout.success');
Route::post('/analytics/events', VisitorAnalyticsController::class)
    ->middleware('throttle:120,1')
    ->name('visitor.analytics.track');
