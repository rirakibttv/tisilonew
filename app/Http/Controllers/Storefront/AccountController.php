<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function login(): View
    {
        return view('storefront.account.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'ইমেইল ঠিকানা লিখুন।',
            'password.required' => 'পাসওয়ার্ড লিখুন।',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'ইমেইল অথবা পাসওয়ার্ড সঠিক নয়।'])->onlyInput('email');
        }

        $request->session()->regenerate();

        if (Auth::user()?->status !== UserStatus::Active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'এই অ্যাকাউন্টটি বর্তমানে সক্রিয় নয়।']);
        }

        $this->mergeGuestWishlist($request, Auth::user());

        return redirect()->intended(route('store.account.dashboard'));
    }

    public function register(): View
    {
        return view('storefront.account.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'name.required' => 'আপনার নাম লিখুন।',
            'email.required' => 'ইমেইল ঠিকানা লিখুন।',
            'email.unique' => 'এই ইমেইল দিয়ে ইতিমধ্যে অ্যাকাউন্ট আছে।',
            'phone.required' => 'মোবাইল নম্বর লিখুন।',
            'phone.unique' => 'এই মোবাইল নম্বর দিয়ে ইতিমধ্যে অ্যাকাউন্ট আছে।',
            'password.required' => 'পাসওয়ার্ড লিখুন।',
            'password.confirmed' => 'দুইটি পাসওয়ার্ড একই হতে হবে।',
        ]);

        $user = User::query()->create([
            ...$validated,
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $this->mergeGuestWishlist($request, $user);

        return redirect()->route('store.account.dashboard')
            ->with('status', 'আপনার Tisilo অ্যাকাউন্ট তৈরি হয়েছে।');
    }

    public function dashboard(): View
    {
        $orders = Auth::user()->orders()
            ->withCount('items')
            ->latest('placed_at')
            ->limit(10)
            ->get();

        return view('storefront.account.dashboard', compact('orders'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('store.home');
    }

    private function mergeGuestWishlist(Request $request, User $user): void
    {
        $productIds = collect($request->session()->pull('store_wishlist', []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique();

        if ($productIds->isNotEmpty()) {
            $existingIds = Product::query()->whereIn('id', $productIds)->pluck('id');
            $user->wishlistProducts()->syncWithoutDetaching($existingIds);
        }

        $request->session()->put(
            'store_wishlist',
            $user->wishlistProducts()->pluck('products.id')->map(fn ($id): int => (int) $id)->all(),
        );
    }
}
