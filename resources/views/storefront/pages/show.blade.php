@extends('layouts.storefront')

@php
    $contentPageTitle = $page['title'].' — '.($generalSettings['site_name'] ?? 'Tisilo');
    $contentMetaDescription = \App\Support\SeoMetadata::description(
        $page['description'] ?? null,
        $page['title'],
    );
@endphp
@section('title', $contentPageTitle)
@section('meta_description', $contentMetaDescription)

@section('content')
    <section class="bg-slate-50 py-10 sm:py-14">
        <div class="storefront-shell">
            <nav class="mb-6 flex items-center gap-2 text-xs font-semibold text-slate-500" aria-label="Breadcrumb">
                <a href="{{ route('store.home') }}" class="transition hover:text-purple-700">Home</a>
                <span>/</span>
                <span class="text-slate-900">{{ $page['name'] }}</span>
            </nav>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="bg-gradient-to-r from-purple-950 via-purple-800 to-fuchsia-800 px-6 py-10 text-white sm:px-12 sm:py-14">
                    <p class="text-xs font-black uppercase tracking-[0.22em] text-purple-200">Tisilo Supermarket</p>
                    <h1 class="mt-3 max-w-3xl text-3xl font-black sm:text-5xl">{{ $page['title'] }}</h1>
                </div>

                <div class="grid gap-10 p-6 sm:p-10 lg:grid-cols-[minmax(0,1fr)_280px] lg:p-12">
                    <article class="storefront-content max-w-none">
                        {!! $page['description'] !!}
                    </article>

                    <aside class="h-fit rounded-2xl border border-purple-100 bg-purple-50 p-6">
                        <h2 class="text-lg font-black text-slate-950">সহায়তা প্রয়োজন?</h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">আমাদের সাপোর্ট টিম অর্ডার ও পণ্য-সংক্রান্ত তথ্য দিয়ে সহায়তা করবে।</p>
                        <div class="mt-5 space-y-3 text-sm font-bold text-slate-800">
                            @if(filled($contactSettings['phone'] ?? $contactSettings['hotline'] ?? null))
                                <a href="tel:{{ $contactSettings['phone'] ?? $contactSettings['hotline'] }}" class="flex items-center gap-2 transition hover:text-purple-700">
                                    @svg('heroicon-o-phone', 'size-5 text-purple-700')
                                    {{ $contactSettings['phone'] ?? $contactSettings['hotline'] }}
                                </a>
                            @endif
                            @if(filled($contactSettings['email'] ?? null))
                                <a href="mailto:{{ $contactSettings['email'] }}" class="flex items-center gap-2 break-all transition hover:text-purple-700">
                                    @svg('heroicon-o-envelope', 'size-5 shrink-0 text-purple-700')
                                    {{ $contactSettings['email'] }}
                                </a>
                            @endif
                        </div>
                        <a href="{{ route('store.shop.index') }}" class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-purple-700 px-4 py-3 text-sm font-black text-white transition hover:bg-purple-800">Shop Now</a>
                    </aside>
                </div>
            </div>
        </div>
    </section>
@endsection
