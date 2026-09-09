@php
    $catalogItems = $this->catalogItems();
    $districtOptions = $this->districtOptions();
    $selectedQuote = $this->selectedQuote();
    $subtotal = $this->subtotal();
    $total = $this->total();
@endphp

<x-filament-panels::page>
    <div class="space-y-4">
        @if ($lastOrderNumber)
            <div class="flex flex-col justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-950 sm:flex-row sm:items-center">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-emerald-600 text-white">@svg('heroicon-o-check', 'size-6')</span>
                    <div><p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Sale completed</p><p class="font-black">Order {{ $lastOrderNumber }} সফলভাবে তৈরি হয়েছে।</p></div>
                </div>
                <a href="{{ \App\Filament\Resources\Orders\Pages\ListOrders::getUrl() }}" class="text-sm font-bold text-emerald-700 hover:text-emerald-900">All Orders দেখুন →</a>
            </div>
        @endif

        <div class="flex justify-end">
            <button type="button" wire:click="clearCart" wire:confirm="বর্তমান POS cart মুছে ফেলবেন?" class="inline-flex items-center gap-2 rounded-xl border border-red-300 bg-white px-4 py-2 text-sm font-bold text-red-600 transition hover:bg-red-50 disabled:opacity-50" @disabled($cart === [])>
                @svg('heroicon-o-trash', 'size-4') Cart Clear
            </button>
        </div>

        <div class="grid min-h-[680px] gap-5 xl:grid-cols-[minmax(0,1.65fr)_minmax(360px,1fr)]">
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="m-4 flex flex-col justify-between gap-3 rounded-2xl bg-gradient-to-r from-indigo-700 to-violet-600 px-5 py-4 text-white sm:flex-row sm:items-center">
                    <div>
                        <p class="text-lg font-black">Tisilo Store</p>
                        <span class="mt-1 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-bold">District Shipping POS</span>
                    </div>
                    <div class="sm:text-right"><p class="text-xs text-indigo-100">Session</p><p class="font-black">{{ $sessionCode }}</p></div>
                </div>

                <div class="px-4 pb-4">
                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="w-full min-w-[680px] text-left text-sm">
                            <thead class="bg-gray-50 text-xs font-bold uppercase tracking-wide text-gray-500 dark:bg-white/5">
                                <tr><th class="w-20 px-4 py-3">Image</th><th class="px-3 py-3">Item</th><th class="w-36 px-3 py-3 text-center">Qty</th><th class="w-28 px-3 py-3 text-right">Price</th><th class="w-32 px-3 py-3 text-right">Subtotal</th><th class="w-12 px-3 py-3"></th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                @forelse ($cart as $key => $line)
                                    <tr wire:key="pos-cart-{{ $key }}">
                                        <td class="px-4 py-3">
                                            <div class="grid size-14 place-items-center overflow-hidden rounded-xl bg-indigo-50 font-black text-indigo-600">
                                                @if ($line['image'])<img src="{{ $line['image'] }}" alt="" class="size-full object-cover">@else{{ mb_strtoupper(mb_substr($line['name'], 0, 1)) }}@endif
                                            </div>
                                        </td>
                                        <td class="px-3 py-3">
                                            <p class="font-black text-gray-950 dark:text-white">{{ $line['name'] }}</p>
                                            @if ($line['option'])<p class="mt-1 text-xs text-indigo-600">{{ $line['option'] }}</p>@endif
                                            @if ($line['sku'])<p class="mt-1 text-[11px] text-gray-400">SKU: {{ $line['sku'] }}</p>@endif
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="mx-auto flex w-fit items-center overflow-hidden rounded-xl border border-gray-200">
                                                <button type="button" wire:click="changeQuantity(@js($key), -1)" class="grid size-9 place-items-center font-black text-gray-600 hover:bg-gray-50">−</button>
                                                <span class="grid h-9 min-w-10 place-items-center border-x border-gray-200 px-2 font-black">{{ $line['quantity'] }}</span>
                                                <button type="button" wire:click="changeQuantity(@js($key), 1)" class="grid size-9 place-items-center font-black text-indigo-600 hover:bg-indigo-50">+</button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 text-right font-semibold">৳{{ number_format($line['price'], 0) }}</td>
                                        <td class="px-3 py-3 text-right font-black">৳{{ number_format($line['price'] * $line['quantity'], 0) }}</td>
                                        <td class="px-3 py-3 text-right"><button type="button" wire:click="removeItem(@js($key))" title="Remove item" class="text-red-500 hover:text-red-700">@svg('heroicon-o-x-mark', 'size-5')</button></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-5 py-14 text-center"><div class="mx-auto grid size-14 place-items-center rounded-2xl bg-indigo-50 text-indigo-500">@svg('heroicon-o-shopping-cart', 'size-7')</div><p class="mt-3 font-bold text-gray-700">ডান পাশ থেকে পণ্য যোগ করুন</p><p class="mt-1 text-xs text-gray-400">পণ্যে ক্লিক করলেই cart-এ যুক্ত হবে।</p></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @error('cart') <p class="mt-2 text-sm font-bold text-red-600">{{ $message }}</p> @enderror

                    <div class="mt-6 grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-[0.16em] text-gray-500">Customer & delivery</h3>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="text-xs font-bold text-gray-700">Customer Name <span class="text-red-500">*</span><input wire:model="customerName" autocomplete="name" placeholder="Customer Name" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"></label>
                                <label class="text-xs font-bold text-gray-700">Mobile Number <span class="text-red-500">*</span><input wire:model="customerPhone" inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"></label>
                                <label class="text-xs font-bold text-gray-700 sm:col-span-2">Address <span class="text-red-500">*</span><textarea wire:model="addressLine" rows="2" autocomplete="street-address" placeholder="বাসা, রোড ও এলাকার বিস্তারিত ঠিকানা" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"></textarea></label>

                                <div x-data="{ open: false }" @click.outside="open = false" class="relative sm:col-span-2">
                                    <label for="pos-district" class="text-xs font-bold text-gray-700">District / জেলা <span class="text-red-500">*</span></label>
                                    <div class="relative mt-1.5">
                                        <input id="pos-district" wire:model.live.debounce.250ms="districtSearch" @focus="open = true" @input="open = true" autocomplete="off" placeholder="জেলা লিখে নির্বাচন করুন" class="block h-11 w-full rounded-xl border-gray-300 pr-10 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                        <span class="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-gray-400">@svg('heroicon-o-chevron-down', 'size-4')</span>
                                    </div>
                                    <div x-show="open" x-transition.opacity class="absolute inset-x-0 top-full z-30 mt-1 max-h-56 overflow-y-auto rounded-xl border border-gray-200 bg-white p-1 shadow-xl" style="display: none;">
                                        @forelse ($districtOptions as $quote)
                                            <button type="button" wire:click="selectDistrict({{ $quote['region_id'] }})" @click="open = false" class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm hover:bg-indigo-50">
                                                <span class="font-bold text-gray-800">{{ $quote['district'] }}</span><span class="text-xs font-black text-indigo-600">৳{{ number_format($quote['amount'], 0) }}</span>
                                            </button>
                                        @empty
                                            <p class="px-3 py-3 text-xs font-semibold text-red-600">{{ $cart === [] ? 'আগে cart-এ পণ্য যোগ করুন।' : 'এই জেলার shipping rate পাওয়া যায়নি।' }}</p>
                                        @endforelse
                                    </div>
                                    @error('districtSearch') <p class="mt-1 text-xs font-bold text-red-600">{{ $message }}</p> @enderror
                                    @error('shippingRegionId') <p class="mt-1 text-xs font-bold text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <label class="text-xs font-bold text-gray-700 sm:col-span-2">Thana / Upazila <span class="text-red-500">*</span><input wire:model="thana" autocomplete="address-level3" placeholder="থানা বা উপজেলার নাম" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"><span class="mt-1 block text-[11px] font-normal text-gray-400">Shipping charge নির্বাচিত জেলা অনুযায়ী হিসাব হবে।</span></label>
                                <label class="text-xs font-bold text-gray-700 sm:col-span-2">Order Note (optional)<textarea wire:model="notes" rows="2" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"></textarea></label>
                            </div>
                            @foreach (['customerName', 'customerPhone', 'addressLine', 'thana'] as $field) @error($field)<p class="mt-1 text-xs font-bold text-red-600">{{ $message }}</p>@enderror @endforeach
                        </div>

                        <div>
                            <h3 class="text-xs font-black uppercase tracking-[0.16em] text-gray-500">Summary</h3>
                            <div class="mt-3 rounded-2xl bg-gray-50 p-5 dark:bg-white/5">
                                <div class="space-y-3 text-sm">
                                    <div class="flex justify-between"><span class="text-gray-500">Sub Total</span><span class="font-bold">৳{{ number_format($subtotal, 0) }}</span></div>
                                    <div class="flex justify-between"><span class="text-gray-500">Shipping Fee</span><span class="font-bold {{ $selectedQuote ? 'text-indigo-600' : 'text-amber-600' }}">{{ $selectedQuote ? '৳'.number_format($selectedQuote['amount'], 0) : 'জেলা নির্বাচন করুন' }}</span></div>
                                    <div class="flex justify-between border-t border-dashed border-gray-300 pt-3 text-lg"><span class="font-black">Grand Total</span><span class="font-black text-emerald-600">৳{{ number_format($total, 0) }}</span></div>
                                </div>
                                @if ($selectedQuote)<p class="mt-3 rounded-xl bg-white px-3 py-2 text-xs text-gray-500">{{ $selectedQuote['district'] }} · {{ implode(', ', $selectedQuote['partners']) }} · আনুমানিক {{ $selectedQuote['estimated_min_days'] }}–{{ $selectedQuote['estimated_max_days'] }} দিন</p>@endif
                                @if ($shippingError)<p class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-xs font-bold text-red-700">{{ $shippingError }}</p>@endif
                                <button type="button" wire:click="completeSale" wire:loading.attr="disabled" wire:target="completeSale" @disabled($cart === [] || ! $selectedQuote) class="mt-5 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 text-sm font-black text-white shadow-lg shadow-indigo-500/20 transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span wire:loading.remove wire:target="completeSale">@svg('heroicon-o-check-circle', 'size-5') Complete Sale</span>
                                    <span wire:loading.flex wire:target="completeSale" class="items-center gap-2">@svg('heroicon-o-arrow-path', 'size-5 animate-spin') Order তৈরি হচ্ছে</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-100 p-4 dark:border-white/10">
                    <div class="flex items-center justify-between gap-3"><h2 class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Products</h2><span class="rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-black text-indigo-600">{{ count($catalogItems) }} items</span></div>
                    <label class="relative mt-3 block">
                        <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-gray-400">@svg('heroicon-o-magnifying-glass', 'size-5')</span>
                        <input wire:model.live.debounce.300ms="search" type="search" placeholder="নাম, SKU বা barcode দিয়ে খুঁজুন..." class="block h-11 w-full rounded-xl border-gray-300 pl-10 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </label>
                </div>

                <div class="max-h-[930px] flex-1 overflow-y-auto p-3">
                    <div class="grid grid-cols-2 gap-3">
                        @forelse ($catalogItems as $item)
                            <button type="button" wire:click="addProduct({{ $item['product_id'] }}, {{ $item['variation_id'] }})" wire:key="catalog-{{ $item['product_id'] }}-{{ $item['variation_id'] }}" class="group relative flex min-h-48 flex-col items-center rounded-2xl border border-indigo-100 bg-indigo-50/60 p-3 text-center transition hover:-translate-y-0.5 hover:border-indigo-300 hover:bg-indigo-50 hover:shadow-md">
                                <span class="absolute left-2 top-2 rounded-full bg-indigo-100 px-2 py-1 text-[10px] font-black text-indigo-700">{{ $item['stock'] === null ? 'In stock' : 'Stock: '.$item['stock'] }}</span>
                                <div class="mt-5 grid size-20 place-items-center overflow-hidden rounded-xl bg-white font-black text-indigo-500 shadow-sm">
                                    @if ($item['image'])<img src="{{ $item['image'] }}" alt="" class="size-full object-cover">@else{{ mb_strtoupper(mb_substr($item['name'], 0, 1)) }}@endif
                                </div>
                                <p class="mt-3 line-clamp-2 text-xs font-black leading-5 text-gray-900">{{ $item['name'] }}</p>
                                @if ($item['option'])<p class="mt-1 line-clamp-1 text-[10px] font-semibold text-indigo-600">{{ $item['option'] }}</p>@endif
                                <p class="mt-auto pt-2 text-sm font-black text-emerald-600">৳{{ number_format($item['price'], 0) }}</p>
                            </button>
                        @empty
                            <div class="col-span-2 py-20 text-center"><div class="mx-auto grid size-14 place-items-center rounded-2xl bg-gray-100 text-gray-400">@svg('heroicon-o-magnifying-glass', 'size-7')</div><p class="mt-3 font-bold text-gray-600">কোনো বিক্রয়যোগ্য পণ্য পাওয়া যায়নি</p><p class="mt-1 text-xs text-gray-400">Search পরিবর্তন করুন অথবা product stock দেখুন।</p></div>
                        @endforelse
                    </div>
                </div>
            </aside>
        </div>
    </div>
</x-filament-panels::page>
