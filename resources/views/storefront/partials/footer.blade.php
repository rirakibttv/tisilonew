<footer class="mt-16 bg-slate-950 text-slate-300">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-8">
        <div>
            <p class="text-2xl font-black text-orange-500">{{ strtoupper($generalSettings['site_name'] ?? 'Tisilo') }}</p>
            <p class="mt-4 max-w-xs text-sm leading-6 text-slate-400">{{ $generalSettings['footer_about_text'] ?? 'বিশ্বস্ত বিক্রেতা, মানসম্মত পণ্য এবং নিরাপদ কেনাকাটার একটি আধুনিক বাংলাদেশি মার্কেটপ্লেস।' }}</p>
            @if(count($socialLinks ?? []))
                <div class="mt-5 flex flex-wrap gap-3 text-xs font-bold">
                    @foreach($socialLinks as $social)
                        @if($social['status'] ?? false)
                            <a href="{{ $social['link'] }}" target="_blank" rel="noopener" class="rounded-full bg-white/10 px-3 py-2 hover:bg-white/20">{{ $social['title'] }}</a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
        <div>
            <h2 class="font-bold text-white">কাস্টমার সেবা</h2>
            <ul class="mt-4 space-y-3 text-sm text-slate-400">
                <li><a href="{{ route('store.products.index') }}" class="hover:text-orange-400">পণ্য খুঁজুন</a></li>
                <li><a href="{{ route('store.account.dashboard') }}" class="hover:text-orange-400">আমার অর্ডার</a></li>
                <li><a href="{{ route('store.wishlist.index') }}" class="hover:text-orange-400">আমার উইশলিস্ট</a></li>
                <li><span>{{ $contactSettings['phone'] ?? $contactSettings['hotline'] ?? 'যোগাযোগ' }}</span></li>
            </ul>
        </div>
        <div>
            <h2 class="font-bold text-white">Tisilo সম্পর্কে</h2>
            <ul class="mt-4 space-y-3 text-sm text-slate-400">
                @forelse($contentPages ?? [] as $contentPage)
                    <li><a href="{{ route('store.pages.show', ['slug' => $contentPage['slug']]) }}" class="hover:text-orange-400">{{ $contentPage['name'] }}</a></li>
                @empty
                    <li><a href="{{ route('store.home') }}" class="hover:text-orange-400">হোম পেজ</a></li>
                @endforelse
                <li><a href="/admin" class="hover:text-orange-400">Seller Center</a></li>
            </ul>
        </div>
        <div>
            <h2 class="font-bold text-white">নিরাপদ কেনাকাটা</h2>
            <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs font-bold">
                <span class="rounded-lg bg-white/10 px-3 py-3">COD</span>
                <span class="rounded-lg bg-white/10 px-3 py-3">bKash</span>
                <span class="rounded-lg bg-white/10 px-3 py-3">Nagad</span>
            </div>
            <p class="mt-4 text-xs leading-5 text-slate-500">Cloudflare CDN ও আধুনিক নিরাপত্তা ব্যবস্থার জন্য প্রস্তুত।</p>
        </div>
    </div>
    <div class="border-t border-white/10 py-5 text-center text-xs text-slate-500">© {{ date('Y') }} {{ $generalSettings['site_name'] ?? 'Tisilo' }}. সর্বস্বত্ব সংরক্ষিত।</div>
</footer>
