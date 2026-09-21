@extends('storefront.account.layout')

@section('title', ($filter === 'to-review' ? 'To Review' : 'All Review').' — Tisilo')

@section('account-content')
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-5 sm:p-6">
            <p class="text-xs font-black uppercase tracking-[0.15em] text-violet-600">My Review</p>
            <h1 class="mt-1 text-2xl font-black text-slate-950">{{ $filter === 'to-review' ? 'To Review' : 'All Review' }}</h1>
            <p class="mt-2 text-sm text-slate-500">শুধু ডেলিভারি সম্পন্ন হওয়া কেনাকাটার জন্য রিভিউ দেওয়া যায়।</p>
        </div>

        @if($filter === 'to-review')
            <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6 xl:grid-cols-3">
                @forelse($reviewableItems as $item)
                    <article class="flex flex-col rounded-2xl border border-slate-200 p-4">
                        <div class="flex gap-3">
                            @if(filled($item->product?->featured_image))
                                <img src="{{ asset('storage/'.ltrim($item->product->featured_image, '/')) }}" alt="{{ $item->product->name }}" class="size-16 rounded-xl object-cover">
                            @else
                                <span class="grid size-16 shrink-0 place-items-center rounded-xl bg-slate-100">@svg('heroicon-o-photo', 'size-6 text-slate-400')</span>
                            @endif
                            <div class="min-w-0">
                                <h2 class="line-clamp-2 font-black text-slate-900">{{ $item->product?->name ?? $item->product_name }}</h2>
                                <p class="mt-1 text-xs text-slate-500">{{ $item->order->order_number }}</p>
                            </div>
                        </div>
                        @if($item->product)
                            <a href="{{ route('store.products.show', $item->product) }}#product-reviews" class="mt-4 inline-flex justify-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-black text-white">Write a review</a>
                        @endif
                    </article>
                @empty
                    <div class="col-span-full py-14 text-center">
                        @svg('heroicon-o-check-badge', 'mx-auto size-12 text-emerald-400')
                        <p class="mt-4 font-black text-slate-800">রিভিউ দেওয়ার মতো নতুন পণ্য নেই</p>
                    </div>
                @endforelse
            </div>
            @if($reviewableItems->hasPages())<div class="border-t border-slate-100 p-5">{{ $reviewableItems->links() }}</div>@endif
        @else
            <div class="divide-y divide-slate-100">
                @forelse($reviews as $review)
                    <article class="p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <a href="{{ $review->product ? route('store.products.show', $review->product) : '#' }}" class="font-black text-slate-900 hover:text-violet-600">{{ $review->product?->name ?? 'Deleted product' }}</a>
                                <div class="mt-2 flex items-center gap-1 text-amber-500">
                                    @for($i = 1; $i <= 5; $i++)<span>{{ $i <= $review->rating ? '★' : '☆' }}</span>@endfor
                                </div>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-black {{ $review->status === 'approved' ? 'bg-emerald-50 text-emerald-700' : ($review->status === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700') }}">{{ ucfirst($review->status) }}</span>
                        </div>
                        @if($review->title)<h3 class="mt-4 font-black text-slate-800">{{ $review->title }}</h3>@endif
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $review->review }}</p>
                    </article>
                @empty
                    <div class="py-16 text-center">@svg('heroicon-o-star', 'mx-auto size-12 text-slate-300')<p class="mt-4 font-black text-slate-800">আপনি এখনো কোনো রিভিউ দেননি</p></div>
                @endforelse
            </div>
            @if($reviews->hasPages())<div class="border-t border-slate-100 p-5">{{ $reviews->links() }}</div>@endif
        @endif
    </div>
@endsection
