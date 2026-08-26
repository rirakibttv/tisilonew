@extends('layouts.storefront')

@section('title', $page['title'].' — '.($generalSettings['site_name'] ?? 'Tisilo'))

@section('content')
    <section class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
            <p class="text-xs font-bold uppercase tracking-[0.22em] text-orange-500">{{ $page['name'] }}</p>
            <h1 class="mt-3 text-3xl font-black text-slate-950 sm:text-4xl">{{ $page['title'] }}</h1>
            <div class="prose prose-slate mt-8 max-w-none">
                {!! $page['description'] !!}
            </div>
        </div>
    </section>
@endsection
