<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\CustomerOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\PaymentMethodService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $orders = $user->orders()
            ->withCount('items')
            ->latest('placed_at')
            ->limit(5)
            ->get();

        $summary = [
            'orders' => $user->orders()->count(),
            'to_pay' => $user->orders()->whereIn('payment_status', [PaymentStatus::Unpaid->value, PaymentStatus::Pending->value])->count(),
            'to_ship' => $user->orders()->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Processing->value])->count(),
            'to_receive' => $user->orders()->where('status', OrderStatus::Shipped->value)->count(),
        ];

        return view('storefront.account.overview', [
            'orders' => $orders,
            'summary' => $summary,
            ...$this->navigationData($user),
        ]);
    }

    public function orders(Request $request, string $filter = 'all'): View
    {
        abort_unless(in_array($filter, ['to-pay', 'to-ship', 'to-receive', 'all'], true), 404);

        $query = $request->user()->orders()->withCount('items')->latest('placed_at');

        match ($filter) {
            'to-pay' => $query->whereIn('payment_status', [PaymentStatus::Unpaid->value, PaymentStatus::Pending->value]),
            'to-ship' => $query->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Processing->value]),
            'to-receive' => $query->where('status', OrderStatus::Shipped->value),
            default => $query,
        };

        return view('storefront.account.orders', [
            'orders' => $query->paginate(12),
            'filter' => $filter,
            ...$this->navigationData($request->user()),
        ]);
    }

    public function reviews(Request $request, string $filter = 'all'): View
    {
        abort_unless(in_array($filter, ['to-review', 'all'], true), 404);

        $reviewableItems = null;
        $reviews = null;

        if ($filter === 'to-review') {
            $reviewableItems = OrderItem::query()
                ->whereHas('order', fn ($query) => $query
                    ->where('user_id', $request->user()->getKey())
                    ->where('status', OrderStatus::Delivered->value))
                ->whereNotNull('product_id')
                ->whereDoesntHave('reviews', fn ($query) => $query->where('user_id', $request->user()->getKey()))
                ->with(['product:id,name,slug,featured_image', 'order:id,order_number,placed_at'])
                ->latest('id')
                ->paginate(12);
        } else {
            $reviews = $request->user()->productReviews()
                ->with('product:id,name,slug,featured_image')
                ->latest()
                ->paginate(12);
        }

        return view('storefront.account.reviews', [
            'filter' => $filter,
            'reviewableItems' => $reviewableItems,
            'reviews' => $reviews,
            ...$this->navigationData($request->user()),
        ]);
    }

    public function orderRequests(Request $request, string $type, string $mode = 'new'): View
    {
        abort_unless(in_array($type, [CustomerOrderRequest::TYPE_RETURN, CustomerOrderRequest::TYPE_CANCELLATION], true), 404);
        abort_unless(in_array($mode, ['new', 'all'], true), 404);

        $eligibleOrders = null;
        $requests = null;

        if ($mode === 'new') {
            $eligibleStatuses = $type === CustomerOrderRequest::TYPE_RETURN
                ? [OrderStatus::Delivered->value]
                : [OrderStatus::Pending->value, OrderStatus::Confirmed->value];

            $eligibleOrders = $request->user()->orders()
                ->whereIn('status', $eligibleStatuses)
                ->whereDoesntHave('customerRequests', fn ($query) => $query->where('type', $type))
                ->withCount('items')
                ->latest('placed_at')
                ->paginate(10);
        } else {
            $requests = $request->user()->orderRequests()
                ->where('type', $type)
                ->with('order:id,order_number,total_amount,placed_at')
                ->latest()
                ->paginate(10);
        }

        return view('storefront.account.order-requests', [
            'type' => $type,
            'mode' => $mode,
            'eligibleOrders' => $eligibleOrders,
            'requests' => $requests,
            ...$this->navigationData($request->user()),
        ]);
    }

    public function storeOrderRequest(Request $request, string $type, Order $order): RedirectResponse
    {
        abort_unless(in_array($type, [CustomerOrderRequest::TYPE_RETURN, CustomerOrderRequest::TYPE_CANCELLATION], true), 404);
        abort_unless($order->user_id === $request->user()->getKey(), 404);

        $eligibleStatuses = $type === CustomerOrderRequest::TYPE_RETURN
            ? [OrderStatus::Delivered]
            : [OrderStatus::Pending, OrderStatus::Confirmed];
        abort_unless(in_array($order->status, $eligibleStatuses, true), 422);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:120'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $request->user()->orderRequests()->firstOrCreate(
            ['order_id' => $order->getKey(), 'type' => $type],
            [...$validated, 'status' => 'pending'],
        );

        return back()->with('status', $type === CustomerOrderRequest::TYPE_RETURN
            ? 'রিটার্ন অনুরোধটি সফলভাবে জমা হয়েছে।'
            : 'বাতিলের অনুরোধটি সফলভাবে জমা হয়েছে।');
    }

    public function profile(Request $request): View
    {
        return view('storefront.account.profile', $this->navigationData($request->user()));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['required', 'string', 'max:32', Rule::unique('users')->ignore($user)],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $user->fill(collect($validated)->only(['name', 'email', 'phone'])->all());
        if (filled($validated['password'] ?? null)) {
            $user->password = $validated['password'];
        }
        $user->save();

        return back()->with('status', 'প্রোফাইল সফলভাবে আপডেট হয়েছে।');
    }

    public function addresses(Request $request): View
    {
        return view('storefront.account.addresses', [
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->latest()->get(),
            ...$this->navigationData($request->user()),
        ]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'address_line' => ['required', 'string', 'max:1000'],
            'district' => ['required', 'string', 'max:100'],
            'thana' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $makeDefault = $request->boolean('is_default') || ! $request->user()->addresses()->exists();
            if ($makeDefault) {
                $request->user()->addresses()->update(['is_default' => false]);
            }
            $request->user()->addresses()->create([...$validated, 'is_default' => $makeDefault]);
        });

        return back()->with('status', 'নতুন ঠিকানা সংরক্ষণ করা হয়েছে।');
    }

    public function defaultAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->getKey(), 404);
        DB::transaction(function () use ($request, $address): void {
            $request->user()->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return back()->with('status', 'ডিফল্ট ঠিকানা পরিবর্তন হয়েছে।');
    }

    public function destroyAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->getKey(), 404);
        $wasDefault = $address->is_default;
        $address->delete();
        if ($wasDefault) {
            $request->user()->addresses()->first()?->update(['is_default' => true]);
        }

        return back()->with('status', 'ঠিকানাটি মুছে দেওয়া হয়েছে।');
    }

    public function paymentOptions(Request $request, PaymentMethodService $paymentMethods): View
    {
        return view('storefront.account.payment-options', [
            'methods' => $paymentMethods->enabled(),
            'selectedMethod' => $request->user()->paymentPreference?->default_method ?? $paymentMethods->default(),
            ...$this->navigationData($request->user()),
        ]);
    }

    public function updatePaymentOption(Request $request, PaymentMethodService $paymentMethods): RedirectResponse
    {
        $validated = $request->validate([
            'default_method' => ['required', Rule::in($paymentMethods->keys())],
        ]);

        $request->user()->paymentPreference()->updateOrCreate([], $validated);

        return back()->with('status', 'ডিফল্ট পেমেন্ট অপশন সংরক্ষণ হয়েছে।');
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

    /** @return array<string, mixed> */
    private function navigationData(User $user): array
    {
        return [
            'accountNavigationCounts' => [
                'wishlist' => $user->wishlistProducts()->count(),
                'toPay' => $user->orders()->whereIn('payment_status', [PaymentStatus::Unpaid->value, PaymentStatus::Pending->value])->count(),
                'toShip' => $user->orders()->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Processing->value])->count(),
                'toReceive' => $user->orders()->where('status', OrderStatus::Shipped->value)->count(),
                'toReview' => OrderItem::query()
                    ->whereHas('order', fn ($query) => $query->where('user_id', $user->getKey())->where('status', OrderStatus::Delivered->value))
                    ->whereNotNull('product_id')
                    ->whereDoesntHave('reviews', fn ($query) => $query->where('user_id', $user->getKey()))
                    ->count(),
                'returns' => $user->orderRequests()->where('type', CustomerOrderRequest::TYPE_RETURN)->count(),
                'cancellations' => $user->orderRequests()->where('type', CustomerOrderRequest::TYPE_CANCELLATION)->count(),
            ],
        ];
    }
}
