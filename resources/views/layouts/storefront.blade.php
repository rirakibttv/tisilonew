<!DOCTYPE html>
<html lang="bn">
<head>
    @php
        $pageTitle = trim($__env->yieldContent('title', $seoSettings['meta_title'] ?? 'Tisilo — আপনার বিশ্বস্ত অনলাইন মার্কেটপ্লেস'));
        $pageDescription = trim($__env->yieldContent('meta_description', $seoSettings['meta_description'] ?? 'Tisilo—বিশ্বস্ত মাল্টি-ভেন্ডর অনলাইন মার্কেটপ্লেস।'));
        $faviconPath = $generalSettings['favicon'] ?? null;
        $faviconUrl = filled($faviconPath)
            ? asset('storage/'.ltrim($faviconPath, '/')).'?v='.substr(sha1($faviconPath), 0, 12)
            : null;
        $ogBannerPath = $generalSettings['og_banner'] ?? null;
        $ogBannerUrl = filled($ogBannerPath)
            ? asset('storage/'.ltrim($ogBannerPath, '/')).'?v='.substr(sha1($ogBannerPath), 0, 12)
            : null;
        $searchVerification = $seoSettings['search_console_verification'] ?? null;
        if (filled($searchVerification) && str_contains($searchVerification, '=')) {
            $searchVerification = Illuminate\Support\Str::after($searchVerification, '=');
        }
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $generalSettings['primary_color'] ?? '#f97316' }}">
    <meta name="description" content="{{ $pageDescription }}">
    @if(filled($seoSettings['meta_tags'] ?? null))
        <meta name="keywords" content="{{ $seoSettings['meta_tags'] }}">
    @endif
    @if(filled($searchVerification))
        <meta name="google-site-verification" content="{{ $searchVerification }}">
    @endif
    @if($faviconUrl)
        <link rel="icon" type="image/png" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" type="image/png" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ $generalSettings['site_name'] ?? 'Tisilo' }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">
    @if($ogBannerUrl)
        <meta property="og:image" content="{{ $ogBannerUrl }}">
    @endif
    <meta name="twitter:card" content="{{ $ogBannerUrl ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <title>{{ $pageTitle }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    @include('storefront.partials.header')

    <main>
        @yield('content')
    </main>

    @include('storefront.partials.footer')
    @include('storefront.partials.visitor-analytics')
    @stack('scripts')
</body>
</html>
