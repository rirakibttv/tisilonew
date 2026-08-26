@extends('layouts.storefront')

@section('title', 'অর্ডার সফল — Tisilo')

@section('content')
    <section class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6">
        <div class="rounded-3xl border border-emerald-200 bg-white px-6 py-14 shadow-sm sm:px-12">
            <div class="mx-auto grid size-20 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                @svg('heroicon-o-check-circle', 'size-12')
            </div>
            <p class="mt-7 text-xs font-bold uppercase tracking-[0.2em] text-emerald-600">Order confirmed</p>
            <h1 class="mt-3 text-3xl font-black text-slate-950">আপনার অর্ডারটি গ্রহণ করা হয়েছে</h1>
            <p class="mt-3 text-sm leading-6 text-slate-500">আমাদের প্রতিনিধি প্রয়োজন হলে আপনার সঙ্গে যোগাযোগ করবেন।</p>

            <div class="mx-auto mt-8 grid max-w-lg gap-4 rounded-2xl bg-slate-50 p-6 text-left sm:grid-cols-2">
                <div><p class="text-xs text-slate-500">অর্ডার নম্বর</p><p class="mt-1 font-black text-slate-900">{{ $order->order_number }}</p></div>
                <div><p class="text-xs text-slate-500">মোট মূল্য</p><p class="mt-1 font-black text-orange-600">৳{{ number_format($order->total_amount, 0) }}</p></div>
                <div><p class="text-xs text-slate-500">পেমেন্ট</p><p class="mt-1 font-black text-slate-900">ক্যাশ অন ডেলিভারি</p></div>
                <div><p class="text-xs text-slate-500">স্ট্যাটাস</p><p class="mt-1 font-black text-amber-600">{{ $order->status->label() }}</p></div>
            </div>

            <a href="{{ route('store.products.index') }}" class="mt-8 inline-flex rounded-xl bg-orange-500 px-7 py-3.5 text-sm font-black text-white shadow-lg shadow-orange-500/20 hover:bg-orange-600">আরও কেনাকাটা করুন</a>
        </div>
    </section>
@endsection
