@extends('layouts.storefront')

@section('title', __('Shopping Cart').' — Tisilo')

@section('content')
    <section class="border-b border-slate-200 bg-white">
        <div class="storefront-shell py-10">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-orange-600">{{ __('Your Basket') }}</p>
            <h1 class="mt-2 text-3xl font-black text-slate-950 sm:text-4xl">{{ __('Shopping Cart') }}</h1>
        </div>
    </section>

    <section class="storefront-shell py-10">
        @if (session('success'))
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-700">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>
        @endif

        @if ($lines->isEmpty())
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-20 text-center">
                @svg('heroicon-o-shopping-cart', 'mx-auto size-14 text-slate-300')
                <h2 class="mt-5 text-xl font-black text-slate-800">{{ __('Your cart is empty') }}</h2>
                <p class="mt-2 text-sm text-slate-500">{{ __('Find products you love and add them to your cart.') }}</p>
                <a href="{{ route('store.products.index') }}" class="mt-6 inline-flex rounded-xl bg-orange-500 px-6 py-3.5 text-sm font-black text-white">{{ __('Start Shopping') }}</a>
            </div>
        @else
            <div class="grid gap-8 lg:grid-cols-[1fr_360px]">
                <div class="space-y-4">
                    @foreach ($lines as $key => $line)
                        <article class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[100px_1fr_auto] sm:items-center">
                            <a href="{{ route('store.products.show', $line['slug']) }}" class="aspect-square overflow-hidden rounded-xl bg-gradient-to-br from-slate-100 to-orange-50">
                                @if ($line['image'])
                                    <img src="{{ $line['image'] }}" alt="{{ $line['name'] }}" class="size-full object-cover">
                                @else
                                    <span class="grid size-full place-items-center text-3xl font-black text-orange-500">{{ mb_strtoupper(mb_substr($line['name'], 0, 1)) }}</span>
                                @endif
                            </a>
                            <div class="min-w-0">
                                <a href="{{ route('store.products.show', $line['slug']) }}" class="font-black text-slate-900 hover:text-orange-600">{{ $line['name'] }}</a>
                                @if ($line['option'])<p class="mt-1 text-xs text-slate-500">{{ $line['option'] }}</p>@endif
                                <p class="mt-1 text-xs text-slate-500">{{ __('Seller') }}: {{ $line['vendor'] }}</p>
                                <p class="mt-3 text-lg font-black text-orange-600">৳{{ number_format($line['price'], 0) }}</p>
                            </div>
                            <div class="flex items-center gap-2 sm:block">
                                <form method="POST" action="{{ route('store.cart.update', $key) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="number" name="quantity" value="{{ $line['quantity'] }}" min="1" max="99" class="h-10 w-20 rounded-xl border border-slate-200 px-2 text-center font-bold">
                                    <button class="h-10 rounded-xl bg-slate-900 px-3 text-xs font-bold text-white">{{ __('Update') }}</button>
                                </form>
                                <form method="POST" action="{{ route('store.cart.destroy', $key) }}" class="mt-2">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs font-bold text-rose-600">{{ __('Remove') }}</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>

                <aside class="h-fit rounded-3xl border border-slate-200 bg-white p-6 shadow-sm lg:sticky lg:top-28">
                    <h2 class="text-xl font-black text-slate-950">{{ __('Order Summary') }}</h2>
                    <div class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span class="font-bold text-slate-900">৳{{ number_format($subtotal, 0) }}</span></div>
                        <div class="flex justify-between text-slate-500"><span>{{ __('Delivery') }}</span><span class="font-bold text-emerald-600">{{ __('Next Step') }}</span></div>
                    </div>
                    <div class="mt-5 flex justify-between border-t border-slate-200 pt-5 text-lg font-black"><span>{{ __('Total') }}</span><span class="text-orange-600">৳{{ number_format($subtotal, 0) }}</span></div>
                    <a href="{{ route('store.checkout.index') }}" class="mt-6 grid h-12 w-full place-items-center rounded-xl bg-orange-500 text-sm font-black text-white shadow-lg shadow-orange-500/20 hover:bg-orange-600">{{ __('Proceed to Checkout') }}</a>
                    <p class="mt-3 text-center text-xs text-slate-400">{{ __('Choose delivery and payment in the next step.') }}</p>
                    <a href="{{ route('store.products.index') }}" class="mt-5 block text-center text-sm font-bold text-orange-600">{{ __('Continue Shopping') }}</a>
                </aside>
            </div>
        @endif
    </section>
@endsection
