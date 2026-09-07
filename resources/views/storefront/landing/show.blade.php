<!DOCTYPE html>
<html lang="bn">
<head>
    @php
        $title = $landingPage->meta_title ?: $landingPage->headline;
        $description = $landingPage->meta_description ?: ($landingPage->subheadline ?: $seoSettings['meta_description'] ?? 'Tisilo special offer');
        $heroPath = $landingPage->hero_image ?: $primaryProduct->featured_image;
        $heroUrl = $heroPath ? asset('storage/'.ltrim($heroPath, '/')) : null;
        $ogPath = $landingPage->og_image ?: $heroPath;
        $ogUrl = $ogPath ? asset('storage/'.ltrim($ogPath, '/')) : null;
        $logoPath = $generalSettings['dark_logo'] ?? $generalSettings['white_logo'] ?? null;
        $logoUrl = $logoPath ? asset('storage/'.ltrim($logoPath, '/')) : null;
        $phone = $contactSettings['phone'] ?? $contactSettings['hotline'] ?? null;
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
        :root { --campaign-color: {{ $landingPage->theme_color ?: '#f97316' }}; }
        .campaign-bg { background-color: var(--campaign-color); }
        .campaign-text { color: var(--campaign-color); }
        .campaign-border { border-color: var(--campaign-color); }
        .campaign-variation-option[aria-pressed="true"] { border-color: var(--campaign-color); box-shadow: 0 0 0 3px color-mix(in srgb, var(--campaign-color) 18%, transparent); }
        .campaign-variation-option[aria-pressed="true"] .campaign-variation-check { display: grid; background-color: var(--campaign-color); }
        html { scroll-behavior: smooth; }
    </style>
</head>
<body class="bg-white text-slate-900 antialiased">
    @if($preview)
        <div class="bg-amber-400 px-4 py-2 text-center text-xs font-black text-slate-950">PREVIEW MODE — এই page এখনো public campaign হিসেবে প্রকাশিত নয়</div>
    @endif

    <header class="border-b border-slate-100 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ route('store.home') }}" aria-label="Tisilo homepage">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $generalSettings['site_name'] ?? 'Tisilo' }}" class="h-10 w-auto">
                @else
                    <span class="text-2xl font-black italic text-slate-950">Tisilo</span>
                @endif
            </a>
            <div class="flex items-center gap-3 text-xs font-bold text-slate-600">
                <span class="hidden rounded-full bg-emerald-50 px-3 py-2 text-emerald-700 sm:inline">✓ নিরাপদ অর্ডার</span>
                @if($phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="rounded-full bg-slate-950 px-4 py-2.5 text-white">সহায়তা: {{ $phone }}</a>@endif
            </div>
        </div>
    </header>

    <main>
        <section class="overflow-hidden bg-slate-950 text-white">
            <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 md:py-20">
                <div>
                    @if($landingPage->hero_badge)
                        <span class="inline-flex rounded-full border border-white/20 bg-white/10 px-4 py-2 text-xs font-black uppercase tracking-wider text-orange-300">{{ $landingPage->hero_badge }}</span>
                    @endif
                    <h1 class="mt-5 text-4xl font-black leading-tight sm:text-5xl lg:text-6xl">{{ $landingPage->headline }}</h1>
                    @if($landingPage->subheadline)<p class="mt-5 max-w-xl text-base leading-8 text-slate-300 sm:text-lg">{{ $landingPage->subheadline }}</p>@endif

                    @if($landingPage->countdown_ends_at && $landingPage->countdown_ends_at->isFuture())
                        <div class="mt-7 inline-flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-5 py-4">
                            <span class="text-xs font-bold text-slate-400">অফার শেষ হবে</span>
                            <strong id="campaign-countdown" data-deadline="{{ $landingPage->countdown_ends_at->toIso8601String() }}" class="font-black text-orange-300">লোড হচ্ছে…</strong>
                        </div>
                    @endif

                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <a href="#order-now" class="campaign-bg rounded-2xl px-7 py-4 text-base font-black text-white shadow-xl transition hover:-translate-y-0.5">{{ $landingPage->cta_text }}</a>
                        <span class="text-sm font-bold text-slate-300">✓ ক্যাশ অন ডেলিভারি &nbsp; ✓ সহজ রিটার্ন</span>
                    </div>
                </div>

                <div class="relative">
                    <div class="absolute -inset-8 rounded-full opacity-20 blur-3xl campaign-bg"></div>
                    <div class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-white/5 p-3 shadow-2xl">
                        @if($heroUrl)
                            <img src="{{ $heroUrl }}" alt="{{ $primaryProduct->name }}" fetchpriority="high" class="aspect-square w-full rounded-[1.5rem] object-cover">
                        @else
                            <div class="grid aspect-square place-items-center rounded-[1.5rem] bg-slate-900 text-8xl font-black text-white/20">{{ mb_strtoupper(mb_substr($primaryProduct->name, 0, 1)) }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="border-b border-slate-100 bg-white">
            <div class="mx-auto grid max-w-6xl grid-cols-2 gap-px bg-slate-100 sm:grid-cols-4">
                @foreach ([['🚚', 'সারাদেশে ডেলিভারি'], ['🛡️', 'নিরাপদ কেনাকাটা'], ['↻', 'সহজ রিটার্ন'], ['💬', 'দ্রুত সাপোর্ট']] as [$icon, $label])
                    <div class="bg-white px-4 py-5 text-center text-sm font-black"><span class="mr-2">{{ $icon }}</span>{{ $label }}</div>
                @endforeach
            </div>
        </section>

        @if($landingPage->offer_title || $landingPage->offer_body)
            <section class="mx-auto max-w-4xl px-4 py-16 text-center sm:px-6">
                @if($landingPage->offer_title)<h2 class="text-3xl font-black text-slate-950 sm:text-4xl">{{ $landingPage->offer_title }}</h2>@endif
                @if($landingPage->offer_body)<div class="prose prose-lg mx-auto mt-6 max-w-none text-left leading-8 text-slate-600 sm:text-center">{!! $landingPage->offer_body !!}</div>@endif
            </section>
        @endif

        @if(count($landingPage->benefits ?? []))
            <section class="bg-slate-50 py-16">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div class="mx-auto max-w-2xl text-center"><p class="campaign-text text-xs font-black uppercase tracking-[0.2em]">কেন এটি আপনার জন্য</p><h2 class="mt-3 text-3xl font-black">যে সুবিধাগুলো সত্যিই কাজে আসবে</h2></div>
                    <div class="mt-10 grid gap-5 md:grid-cols-3">
                        @foreach($landingPage->benefits as $benefit)
                            <article class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm">
                                <span class="campaign-bg grid size-11 place-items-center rounded-2xl text-lg font-black text-white">{{ $loop->iteration }}</span>
                                <h3 class="mt-5 text-lg font-black">{{ $benefit['title'] ?? '' }}</h3>
                                @if($benefit['description'] ?? null)<p class="mt-3 text-sm leading-7 text-slate-500">{{ $benefit['description'] }}</p>@endif
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if($landingPage->video_embed_url)
            <section class="mx-auto max-w-5xl px-4 py-16 sm:px-6">
                <div class="overflow-hidden rounded-3xl bg-slate-950 shadow-2xl">
                    <iframe src="{{ $landingPage->video_embed_url }}" title="{{ $headerTitle }} video" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen class="aspect-video w-full"></iframe>
                </div>
            </section>
        @endif

        @if(count($landingPage->gallery_images ?? []))
            <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <h2 class="text-center text-3xl font-black">বাস্তব ছবিতে আরও বিস্তারিত</h2>
                <div class="mt-9 grid grid-cols-2 gap-4 md:grid-cols-4">
                    @foreach($landingPage->gallery_images as $image)
                        <img src="{{ asset('storage/'.ltrim($image, '/')) }}" alt="{{ $primaryProduct->name }} gallery {{ $loop->iteration }}" loading="lazy" class="aspect-square w-full rounded-2xl object-cover shadow-sm">
                    @endforeach
                </div>
            </section>
        @endif

        @if(count($landingPage->reviews ?? []))
            <section class="bg-slate-950 py-16 text-white">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <h2 class="text-center text-3xl font-black">{{ $landingPage->trust_title ?: 'গ্রাহকের অভিজ্ঞতা' }}</h2>
                    <div class="mt-9 grid gap-5 md:grid-cols-3">
                        @foreach($landingPage->reviews as $review)
                            <figure class="rounded-3xl border border-white/10 bg-white/5 p-7">
                                <div class="text-amber-400">{{ str_repeat('★', (int) ($review['rating'] ?? 5)) }}</div>
                                <blockquote class="mt-4 text-sm leading-7 text-slate-300">“{{ $review['quote'] ?? '' }}”</blockquote>
                                <figcaption class="mt-5 font-black">{{ $review['name'] ?? 'Verified Customer' }}</figcaption>
                            </figure>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @include('storefront.landing.checkout')

        @if(count($landingPage->faqs ?? []))
            <section class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
                <h2 class="text-center text-3xl font-black">সাধারণ প্রশ্ন ও উত্তর</h2>
                <div class="mt-8 space-y-3">
                    @foreach($landingPage->faqs as $faq)
                        <details class="group rounded-2xl border border-slate-200 bg-white p-5">
                            <summary class="cursor-pointer list-none pr-8 font-black">{{ $faq['question'] ?? '' }}</summary>
                            <p class="mt-4 text-sm leading-7 text-slate-500">{{ $faq['answer'] ?? '' }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif
    </main>

    <footer class="bg-slate-950 px-4 py-10 text-center text-sm text-slate-400">
        <p class="font-black text-white">{{ $generalSettings['site_name'] ?? 'Tisilo' }}</p>
        <p class="mt-2">নিরাপদ অর্ডার · ক্যাশ অন ডেলিভারি · সারাদেশে ডেলিভারি</p>
    </footer>

    <a href="#order-now" data-campaign-sticky-cta class="campaign-bg fixed inset-x-4 bottom-4 z-40 grid h-14 place-items-center rounded-2xl text-sm font-black text-white shadow-2xl md:hidden">{{ $landingPage->cta_text }}</a>

    @include('storefront.partials.visitor-analytics')
    <script>
        (function () {
            var countdown = document.getElementById('campaign-countdown');
            if (countdown) {
                var deadline = new Date(countdown.dataset.deadline).getTime();
                var render = function () {
                    var distance = Math.max(0, deadline - Date.now());
                    var days = Math.floor(distance / 86400000);
                    var hours = Math.floor((distance % 86400000) / 3600000);
                    var minutes = Math.floor((distance % 3600000) / 60000);
                    var seconds = Math.floor((distance % 60000) / 1000);
                    countdown.textContent = [days ? days + ' দিন' : '', hours + ' ঘণ্টা', minutes + ' মিনিট', seconds + ' সেকেন্ড'].filter(Boolean).join(' ');
                };
                render();
                window.setInterval(render, 1000);
            }

        })();
    </script>
</body>
</html>
