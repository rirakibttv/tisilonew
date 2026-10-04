<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogCartLineService;
use App\Services\CheckoutService;
use App\Services\ShippingRateService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosSystem extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'pos';

    protected string $view = 'filament.pages.pos-system';

    protected Width|string|null $maxContentWidth = 'full';

    public string $search = '';

    /** @var array<string, array<string, mixed>> */
    public array $cart = [];

    /** @var array<int, array<string, mixed>> */
    public array $shippingQuotes = [];

    public ?int $shippingRegionId = null;

    public string $districtSearch = '';

    public string $customerName = '';

    public string $customerPhone = '';

    public string $addressLine = '';

    public string $thana = '';

    public string $notes = '';

    public ?string $shippingError = null;

    public ?string $lastOrderNumber = null;

    public string $sessionCode = '';

    public function mount(): void
    {
        $this->newSessionCode();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('pos.manage') ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Point of Sale';
    }

    public function getSubheading(): ?string
    {
        return 'দ্রুত পণ্য নির্বাচন, জেলা-ভিত্তিক shipping charge এবং inventory-safe order entry';
    }

    /** @return array<int, array<string, mixed>> */
    public function catalogItems(): array
    {
        $search = trim($this->search);

        return Product::query()
            ->where('status', 'published')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($products) use ($search): void {
                    $like = '%'.$search.'%';
                    $products
                        ->where('name', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('barcode', 'like', $like)
                        ->orWhereHas('variations', fn ($variations) => $variations->where(
                            fn ($fields) => $fields
                                ->where('sku', 'like', $like)
                                ->orWhere('barcode', 'like', $like),
                        ));
                });
            })
            ->with(['variations' => fn ($query) => $query
                ->where('status', true)
                ->with('attributeValues.attribute:id,name')
                ->orderByDesc('is_default')
                ->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(60)
            ->get()
            ->flatMap(function (Product $product): array {
                if ($product->product_type === 'variable') {
                    return $product->variations
                        ->filter(fn ($variation): bool => $variation->stock_quantity > 0
                            && (float) ($variation->sale_price ?? $variation->regular_price) > 0)
                        ->map(fn ($variation): array => $this->catalogPresentation($product, $variation))
                        ->all();
                }

                $price = (float) ($product->sale_price ?? $product->regular_price);
                if ($price <= 0 || ($product->manage_stock && $product->stock_quantity <= 0)) {
                    return [];
                }

                return [$this->catalogPresentation($product)];
            })
            ->values()
            ->all();
    }

    public function addProduct(int $productId, int $variationId = 0): void
    {
        $this->resetErrorBag('cart');

        $product = Product::query()->where('status', 'published')->findOrFail($productId);
        $line = app(CatalogCartLineService::class)->make($product, $variationId ?: null);
        $key = (string) $line['key'];
        $quantity = (int) ($this->cart[$key]['quantity'] ?? 0) + 1;

        if (! $line['backorders_allowed'] && (int) $line['available'] < $quantity) {
            $this->addError('cart', $product->name.'–এর পর্যাপ্ত স্টক নেই।');

            return;
        }

        $line['quantity'] = $quantity;
        $this->cart[$key] = $line;
        $this->refreshShippingQuotes();
    }

    public function changeQuantity(string $key, int $change): void
    {
        if (! isset($this->cart[$key])) {
            return;
        }

        $quantity = max(1, min(99, (int) $this->cart[$key]['quantity'] + $change));
        if (! $this->cart[$key]['backorders_allowed'] && (int) $this->cart[$key]['available'] < $quantity) {
            $this->addError('cart', $this->cart[$key]['name'].'–এর পর্যাপ্ত স্টক নেই।');

            return;
        }

        $this->cart[$key]['quantity'] = $quantity;
        $this->refreshShippingQuotes();
    }

    public function removeItem(string $key): void
    {
        unset($this->cart[$key]);
        $this->refreshShippingQuotes();
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->resetCheckoutState();
    }

    public function selectDistrict(int $regionId): void
    {
        $quote = $this->shippingQuotes[$regionId] ?? null;
        if (! $quote) {
            return;
        }

        $this->shippingRegionId = $regionId;
        $this->districtSearch = (string) $quote['district'];
        $this->resetErrorBag(['shippingRegionId', 'districtSearch']);
    }

    public function updatedDistrictSearch(string $value): void
    {
        $match = collect($this->districtOptions())->first(
            fn (array $quote): bool => mb_strtolower(trim((string) $quote['district'])) === mb_strtolower(trim($value)),
        );

        $this->shippingRegionId = $match ? (int) $match['region_id'] : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function districtOptions(): array
    {
        return collect($this->shippingQuotes)
            ->unique(fn (array $quote): string => mb_strtolower(trim((string) $quote['district'])))
            ->filter(function (array $quote): bool {
                $search = mb_strtolower(trim($this->districtSearch));

                return $search === '' || str_contains(mb_strtolower((string) $quote['district']), $search);
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    public function selectedQuote(): ?array
    {
        return $this->shippingRegionId ? ($this->shippingQuotes[$this->shippingRegionId] ?? null) : null;
    }

    public function subtotal(): float
    {
        return round((float) collect($this->cart)->sum(
            fn (array $line): float => (float) $line['price'] * (int) $line['quantity'],
        ), 2);
    }

    public function total(): float
    {
        return round($this->subtotal() + (float) ($this->selectedQuote()['amount'] ?? 0), 2);
    }

    public function completeSale(CheckoutService $checkout, ShippingRateService $shippingRates): void
    {
        $this->resetErrorBag();

        if ($this->cart === []) {
            $this->addError('cart', 'অর্ডারে কমপক্ষে একটি পণ্য যোগ করুন।');

            return;
        }

        $validated = $this->validate([
            'customerName' => ['required', 'string', 'max:255'],
            'customerPhone' => ['required', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'addressLine' => ['required', 'string', 'max:1000'],
            'districtSearch' => ['required', 'string', 'max:120'],
            'shippingRegionId' => ['required', 'integer'],
            'thana' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'customerName.required' => 'Customer name লিখুন।',
            'customerPhone.required' => 'Mobile number লিখুন।',
            'customerPhone.regex' => 'সঠিক বাংলাদেশি mobile number লিখুন।',
            'addressLine.required' => 'সম্পূর্ণ ঠিকানা লিখুন।',
            'districtSearch.required' => 'জেলা নির্বাচন করুন।',
            'shippingRegionId.required' => 'Dropdown থেকে জেলা নির্বাচন করুন।',
            'thana.required' => 'থানা/উপজেলা লিখুন।',
        ]);

        try {
            $quotes = $shippingRates->quotesForCart(collect($this->cart));
        } catch (ValidationException $exception) {
            $this->shippingError = collect($exception->errors())->flatten()->first();

            return;
        }

        $quote = $quotes->get((int) $validated['shippingRegionId']);
        if (! $quote || mb_strtolower(trim((string) $quote['district'])) !== mb_strtolower(trim($validated['districtSearch']))) {
            $this->addError('shippingRegionId', 'Dropdown থেকে একটি বৈধ জেলা নির্বাচন করুন।');

            return;
        }

        $customerId = User::query()
            ->where('role', UserRole::Customer->value)
            ->where('phone', $validated['customerPhone'])
            ->value('id');

        $order = $checkout->place(collect($this->cart), [
            'checkout_reference' => 'pos-'.Str::uuid(),
            'user_id' => $customerId,
            'customer_name' => $validated['customerName'],
            'customer_phone' => $validated['customerPhone'],
            'payment_method' => 'cod',
            'channel' => 'pos',
            'address_line' => $validated['addressLine'],
            'division' => $quote['division'],
            'district' => $quote['district'],
            'upazila' => $validated['thana'],
            'postal_code' => $quote['postal_code'],
            'notes' => filled($validated['notes']) ? $validated['notes'] : null,
        ], $quote);

        $order->update([
            'status' => OrderStatus::Confirmed,
            'confirmed_at' => now(),
            'confirmed_by' => auth()->id(),
        ]);

        $this->lastOrderNumber = $order->order_number;
        $this->cart = [];
        $this->resetCheckoutState(keepLastOrder: true);

        Notification::make()
            ->success()
            ->title('Sale completed')
            ->body("Order {$order->order_number} তৈরি হয়েছে।")
            ->send();
    }

    private function refreshShippingQuotes(): void
    {
        $this->shippingError = null;

        if ($this->cart === []) {
            $this->shippingQuotes = [];
            $this->shippingRegionId = null;
            $this->districtSearch = '';

            return;
        }

        try {
            $quotes = app(ShippingRateService::class)->quotesForCart(collect($this->cart));
            $this->shippingQuotes = $quotes->all();
        } catch (ValidationException $exception) {
            $this->shippingQuotes = [];
            $this->shippingRegionId = null;
            $this->shippingError = collect($exception->errors())->flatten()->first();

            return;
        }

        $current = $this->shippingRegionId ? ($this->shippingQuotes[$this->shippingRegionId] ?? null) : null;
        if ($current) {
            $this->districtSearch = (string) $current['district'];

            return;
        }

        $sameDistrict = collect($this->shippingQuotes)->first(
            fn (array $quote): bool => mb_strtolower(trim((string) $quote['district'])) === mb_strtolower(trim($this->districtSearch)),
        );
        $this->shippingRegionId = $sameDistrict ? (int) $sameDistrict['region_id'] : null;
        if (! $sameDistrict) {
            $this->districtSearch = '';
        }
    }

    private function resetCheckoutState(bool $keepLastOrder = false): void
    {
        $this->shippingQuotes = [];
        $this->shippingRegionId = null;
        $this->districtSearch = '';
        $this->customerName = '';
        $this->customerPhone = '';
        $this->addressLine = '';
        $this->thana = '';
        $this->notes = '';
        $this->shippingError = null;
        if (! $keepLastOrder) {
            $this->lastOrderNumber = null;
        }
        $this->newSessionCode();
        $this->resetErrorBag();
    }

    private function newSessionCode(): void
    {
        $this->sessionCode = 'POS-'.now()->format('ymd-His').'-'.Str::upper(Str::random(4));
    }

    /** @return array<string, mixed> */
    private function catalogPresentation(Product $product, mixed $variation = null): array
    {
        $path = $variation?->image ?: $product->featured_image;
        $option = $variation?->attributeValues
            ?->map(fn ($value) => $value->attribute->name.': '.$value->value)
            ->join(', ');

        return [
            'product_id' => $product->getKey(),
            'variation_id' => $variation?->getKey() ?? 0,
            'name' => $product->name,
            'option' => $option,
            'sku' => $variation?->sku ?? $product->sku,
            'image' => filled($path) ? asset('storage/'.ltrim($path, '/')) : null,
            'price' => (float) ($variation?->sale_price ?? $variation?->regular_price ?? $product->sale_price ?? $product->regular_price),
            'stock' => $variation
                ? (int) $variation->stock_quantity
                : ($product->manage_stock ? (int) $product->stock_quantity : null),
        ];
    }
}
