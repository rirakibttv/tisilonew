<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $generalSettings['primary_color'] ?? '#f97316' }}">
    <meta name="description" content="@yield('meta_description', $seoSettings['meta_description'] ?? 'Tisilo—বিশ্বস্ত মাল্টি-ভেন্ডর অনলাইন মার্কেটপ্লেস।')">
    @if(filled($seoSettings['meta_tags'] ?? null))
        <meta name="keywords" content="{{ $seoSettings['meta_tags'] }}">
    @endif
    @if(filled($seoSettings['search_console_verification'] ?? null))
        <meta name="google-site-verification" content="{{ $seoSettings['search_console_verification'] }}">
    @endif
    @if(filled($generalSettings['favicon'] ?? null))
        <link rel="icon" href="{{ asset('storage/'.$generalSettings['favicon']) }}">
    @endif
    <title>@yield('title', $seoSettings['meta_title'] ?? 'Tisilo — আপনার বিশ্বস্ত অনলাইন মার্কেটপ্লেস')</title>
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
