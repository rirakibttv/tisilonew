<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    @php
        $title = \App\Support\SeoMetadata::title($landingPage->headline, $landingPage->header_title ?: $landingPage->name);
        $description = \App\Support\SeoMetadata::description(
            $landingPage->subheadline,
            $landingPage->offer_body,
            $primaryProduct->short_description,
            $primaryProduct->description,
            $title,
        );
        $heroPath = $landingPage->hero_image ?: $primaryProduct->featured_image;
        $heroUrl = $heroPath ? asset('storage/'.ltrim($heroPath, '/')) : null;
        $ogPath = $landingPage->og_image ?: $heroPath;
        $ogUrl = $ogPath ? asset('storage/'.ltrim($ogPath, '/')) : null;
        $logoPath = $generalSettings['white_logo'] ?? $generalSettings['dark_logo'] ?? null;
        $logoUrl = $logoPath ? asset('storage/'.ltrim($logoPath, '/')) : null;
        $phone = $contactSettings['phone'] ?? $contactSettings['hotline'] ?? '01794313455';
        $whatsapp = $contactSettings['whatsapp'] ?? $phone;
        $whatsappUrl = 'https://wa.me/88'.ltrim(preg_replace('/[^0-9]/', '', $whatsapp), '88');
        $detailBody = filled(strip_tags((string) $landingPage->offer_body)) ? $landingPage->offer_body : $primaryProduct->description;
        $campaignImages = collect([$heroUrl])
            ->merge(collect($landingPage->gallery_images ?? [])->map(fn ($image) => asset('storage/'.ltrim($image, '/'))))
            ->merge(collect($primaryProduct->gallery_images ?? [])->map(fn ($image) => asset('storage/'.ltrim($image, '/'))))
            ->filter()->unique()->values();
        $galleryImages = collect(range(0, 2))->map(fn ($index) => $campaignImages->isNotEmpty() ? $campaignImages[$index % $campaignImages->count()] : null)->filter();
        $reviewImages = collect(range(0, 4))->map(fn ($index) => $campaignImages->isNotEmpty() ? $campaignImages[$index % $campaignImages->count()] : null)->filter();
        $deadline = $landingPage->countdown_ends_at?->toIso8601String();
        $headerTitle = $landingPage->header_title ?: $landingPage->headline;
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="{{ $preview ? 'noindex,nofollow' : 'index,follow' }}">
    <link rel="canonical" href="{{ route('store.landing.show', $landingPage) }}">
    <meta property="og:type" content="product">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ route('store.landing.show', $landingPage) }}">
    @if($ogUrl)<meta property="og:image" content="{{ $ogUrl }}">@endif
    <meta name="twitter:card" content="summary_large_image">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { --campaign-color: {{ $landingPage->theme_color ?: '#dc2946' }}; --campaign-green: #07852f; --campaign-green-dark: #064c22; }
        html { scroll-behavior: smooth; }
        .campaign-bg { background-color: var(--campaign-color); }
        .campaign-text { color: var(--campaign-color); }
        .campaign-green-bg { background-color: var(--campaign-green); }
        .campaign-green-text { color: var(--campaign-green); }
        .campaign-green-border { border-color: var(--campaign-green); }
        .campaign-variation-option[aria-pressed="true"] { border-color: var(--campaign-green); box-shadow: 0 0 0 3px rgb(7 133 47 / 16%); }
        .campaign-variation-option[aria-pressed="true"] .campaign-variation-check { display: grid; background-color: var(--campaign-green); }
        .campaign-copy > * + * { margin-top: .85rem; }
        .campaign-copy h2, .campaign-copy h3 { color: #111827; font-weight: 900; }
        .campaign-copy h2 { margin-top: 1.8rem; font-size: 1.35rem; }
        .campaign-copy h3 { margin-top: 1.35rem; font-size: 1.1rem; }
        .campaign-copy ul, .campaign-copy ol { padding-left: 1.4rem; }
        .campaign-copy ul { list-style: disc; }
        .campaign-copy ol { list-style: decimal; }
    </style>
</head>
<body class="bg-white text-slate-900 antialiased">
    @if($preview)
        <div class="bg-amber-400 px-4 py-2 text-center text-xs font-black text-slate-950">{{ __('PREVIEW MODE — This page has not been published as a public campaign yet.') }}</div>
    @endif

    <header class="bg-gradient-to-r from-[#053b19] via-[#079433] to-[#053b19] text-white shadow-md">
        <div class="storefront-shell grid min-h-24 items-center gap-4 py-4 md:grid-cols-[180px_minmax(0,1fr)_auto]">
            <a href="{{ route('store.home') }}" class="justify-self-center md:justify-self-start" aria-label="{{ __('Tisilo homepage') }}">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $generalSettings['site_name'] ?? 'Tisilo' }}" class="h-10 w-auto object-contain">
                @else
                    <span class="font-serif text-3xl font-black italic">{{ $generalSettings['site_name'] ?? 'Tisilo' }}</span>
                @endif
            </a>
            <p class="text-center text-lg font-black sm:text-xl">{{ $headerTitle }}</p>
            <div id="campaign-countdown" data-deadline="{{ $deadline }}" class="grid grid-cols-4 gap-1.5" aria-label="{{ __('Offer time remaining') }}">
                @foreach([['days', 'Days'], ['hours', 'Hours'], ['minutes', 'Minutes'], ['seconds', 'Seconds']] as [$part, $label])
                    <div class="min-w-16 rounded-xl border border-dashed border-white/80 bg-white/5 px-2 py-2 text-center">
                        <strong data-countdown-{{ $part }} class="block text-base leading-none">00</strong>
                        <span class="mt-1 block text-[9px] font-semibold text-amber-200">{{ __($label) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </header>

    <main>
        <section class="storefront-shell grid items-stretch gap-4 py-6 lg:grid-cols-2">
            <div class="flex flex-col gap-5 py-1">
                <div class="campaign-green-border grid min-h-28 place-items-center border-2 border-dashed px-6 py-5 text-center">
                    <h1 class="text-3xl font-black leading-tight sm:text-4xl">{{ $landingPage->headline }}</h1>
                </div>
                <div class="campaign-green-border flex min-h-64 flex-1 items-start justify-center border-x-2 border-y-4 border-dashed px-7 py-7 text-center">
                    <p class="max-w-xl whitespace-pre-line text-2xl font-semibold leading-relaxed sm:text-3xl">{{ $landingPage->subheadline ?: $primaryProduct->short_description }}</p>
                </div>
                <div class="text-center">
                    <a href="#order-now" class="campaign-bg inline-flex min-h-14 items-center justify-center rounded-md border-2 border-amber-500 px-8 py-3 text-lg font-black text-white shadow-lg transition hover:-translate-y-0.5">{{ __('Click to Order') }} 🛒</a>
                </div>
            </div>
            <div class="overflow-hidden bg-slate-100 shadow-sm">
                @if($heroUrl)
                    <img src="{{ $heroUrl }}" alt="{{ $primaryProduct->name }}" fetchpriority="high" class="aspect-square h-full w-full object-cover">
                @else
                    <div class="campaign-green-bg grid aspect-square h-full w-full place-items-center text-8xl font-black text-white/30">{{ mb_strtoupper(mb_substr($primaryProduct->name, 0, 1)) }}</div>
                @endif
            </div>
        </section>

        <section class="border-b-[18px] border-rose-100 bg-gradient-to-b from-[#fff7b7] to-[#ffe8d1] px-4 py-10 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <p class="border border-dashed border-fuchsia-300 bg-fuchsia-50/80 px-5 py-4 text-lg font-bold leading-relaxed">{{ __('Call this number to learn more') }}<br><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="text-2xl font-black">{{ $phone }}</a></p>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="rounded-md border-2 border-white bg-rose-600 px-6 py-4 text-lg font-black text-white shadow-sm">☎ {{ __('Call Us') }}</a>
                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="rounded-md border-2 border-white bg-emerald-700 px-6 py-4 text-lg font-black text-white shadow-sm">◉ {{ __('WhatsApp') }}</a>
                </div>
            </div>
        </section>

        <section class="storefront-shell border-x border-slate-200 py-7">
            <h2 class="border-b border-slate-200 pb-3 text-center font-serif text-2xl font-black">{{ $landingPage->offer_title ?: $primaryProduct->name }}</h2>
            <article class="campaign-copy mt-4 text-sm leading-7 text-slate-700">{!! $detailBody !!}</article>
            @if(count($landingPage->benefits ?? []))
                <div class="mt-8 grid gap-4 sm:grid-cols-3">
                    @foreach($landingPage->benefits as $benefit)
                        <article class="rounded-lg border border-slate-200 p-5"><h3 class="font-black">{{ $benefit['title'] ?? '' }}</h3><p class="mt-2 text-sm leading-6 text-slate-600">{{ $benefit['description'] ?? '' }}</p></article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="storefront-shell mt-6">
            <div class="campaign-green-border rounded-md border-4 bg-white p-3 sm:p-6">
                <h2 class="rounded-md bg-[#28633a] px-5 py-3 text-center font-serif text-2xl font-black text-white shadow-md">{{ $landingPage->offer_title ?: $headerTitle }}</h2>
                @if($galleryImages->isNotEmpty())
                    <div class="mt-5 grid gap-2 sm:grid-cols-3">@foreach($galleryImages as $image)<img src="{{ $image }}" alt="{{ __(':product image :number', ['product' => $primaryProduct->name, 'number' => $loop->iteration]) }}" loading="lazy" class="aspect-square w-full object-cover">@endforeach</div>
                @endif
                <div class="mt-4 text-center"><a href="#order-now" class="campaign-bg inline-flex rounded-md border-2 border-amber-500 px-7 py-3 text-lg font-black text-white shadow-lg">{{ __('Click to Order') }} 🛒</a></div>
            </div>
        </section>

        @if($landingPage->video_embed_url)
            <section class="storefront-shell py-8"><div class="campaign-green-border overflow-hidden rounded-lg border-4 shadow-xl"><iframe src="{{ $landingPage->video_embed_url }}" title="{{ $headerTitle }} video" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen class="aspect-video w-full"></iframe></div></section>
        @endif

        @include('storefront.landing.checkout-classic')

        <section class="storefront-shell pb-12">
            <h2 class="campaign-green-bg px-5 py-3 text-center text-2xl font-black text-white">{{ __('Customer Reviews') }}</h2>
            @if($reviewImages->isNotEmpty())
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5">@foreach($reviewImages as $image)<img src="{{ $image }}" alt="{{ __('Customer review image :number', ['number' => $loop->iteration]) }}" loading="lazy" class="aspect-square w-full object-cover">@endforeach</div>
            @endif
            @if(count($landingPage->reviews ?? []))
                <div class="mt-6 grid gap-4 md:grid-cols-3">@foreach($landingPage->reviews as $review)<figure class="rounded-xl border border-slate-200 p-5"><div class="text-amber-500">{{ str_repeat('★', (int) ($review['rating'] ?? 5)) }}</div><blockquote class="mt-3 text-sm leading-6 text-slate-600">“{{ $review['quote'] ?? '' }}”</blockquote><figcaption class="mt-3 font-black">{{ $review['name'] ?? __('Verified Customer') }}</figcaption></figure>@endforeach</div>
            @endif
            <div class="mt-6 text-center"><a href="#order-now" class="campaign-bg inline-flex rounded-md border-2 border-amber-500 px-7 py-3 text-lg font-black text-white shadow-lg">{{ __('Click to Order') }} 🛒</a></div>
        </section>

        @if(count($landingPage->faqs ?? []))
            <section class="storefront-shell pb-14"><h2 class="campaign-green-bg px-5 py-3 text-center text-2xl font-black text-white">{{ __('Frequently Asked Questions') }}</h2><div class="mt-5 space-y-3">@foreach($landingPage->faqs as $faq)<details class="rounded-xl border border-slate-200 p-5"><summary class="cursor-pointer font-black">{{ $faq['question'] ?? '' }}</summary><p class="mt-3 text-sm leading-7 text-slate-600">{{ $faq['answer'] ?? '' }}</p></details>@endforeach</div></section>
        @endif

        @include('storefront.landing.related-products')
    </main>

    <footer class="campaign-green-bg px-4 py-7 text-center text-sm text-white"><p class="font-black">{{ $generalSettings['site_name'] ?? 'Tisilo' }}</p><p class="mt-1 text-white/80">{{ __('Secure Order · Cash on Delivery · Nationwide Delivery') }}</p></footer>
    <a href="#order-now" data-campaign-sticky-cta class="campaign-bg fixed inset-x-4 bottom-4 z-40 grid h-14 place-items-center rounded-xl text-sm font-black text-white shadow-2xl md:hidden">{{ $landingPage->cta_text }}</a>

    @include('storefront.partials.visitor-analytics')
    <script>
        (function () {
            var countdown = document.getElementById('campaign-countdown');
            if (!countdown) return;
            var deadline = countdown.dataset.deadline ? new Date(countdown.dataset.deadline).getTime() : 0;
            var render = function () {
                var distance = Math.max(0, deadline - Date.now());
                var values = { days: Math.floor(distance / 86400000), hours: Math.floor((distance % 86400000) / 3600000), minutes: Math.floor((distance % 3600000) / 60000), seconds: Math.floor((distance % 60000) / 1000) };
                Object.keys(values).forEach(function (part) { var target = countdown.querySelector('[data-countdown-' + part + ']'); if (target) target.textContent = String(values[part]).padStart(2, '0'); });
            };
            render();
            window.setInterval(render, 1000);
        })();
    </script>
</body>
</html>
