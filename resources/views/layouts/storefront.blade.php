<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f97316">
    <meta name="description" content="Tisilo—বিশ্বস্ত মাল্টি-ভেন্ডর অনলাইন মার্কেটপ্লেস। সেরা পণ্য, সেরা দাম ও নিরাপদ কেনাকাটা।">
    <title>@yield('title', 'Tisilo — আপনার বিশ্বস্ত অনলাইন মার্কেটপ্লেস')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    @include('storefront.partials.header')

    <main>
        @yield('content')
    </main>

    @include('storefront.partials.footer')
</body>
</html>
