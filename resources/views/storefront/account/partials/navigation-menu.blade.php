<div class="space-y-4">
    <a href="{{ route('store.wishlist.index') }}" class="{{ $linkClass }} {{ $routeName === 'store.wishlist.index' ? $activeClass : $idleClass }}">
        <span class="flex items-center gap-2.5">@svg('heroicon-o-heart', 'size-5') My Wishlist</span>
        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">{{ $counts['wishlist'] ?? 0 }}</span>
    </a>

    <div>
        <p class="mb-1.5 flex items-center gap-2 px-3 text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">@svg('heroicon-o-shopping-bag', 'size-4') Order Info</p>
        <div class="space-y-1 border-l border-violet-100 pl-2">
            @foreach([['to-pay', 'To Pay', $counts['toPay'] ?? 0], ['to-ship', 'To Ship', $counts['toShip'] ?? 0], ['to-receive', 'To Receive', $counts['toReceive'] ?? 0], ['all', 'All Order', null]] as [$key, $label, $count])
                <a href="{{ route('store.account.orders', $key) }}" class="{{ $linkClass }} {{ $routeName === 'store.account.orders' && $routeFilter === $key ? $activeClass : $idleClass }}"><span>{{ $label }}</span>@if($count !== null)<span class="text-xs">{{ $count }}</span>@endif</a>
            @endforeach
        </div>
    </div>

    <div>
        <p class="mb-1.5 flex items-center gap-2 px-3 text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">@svg('heroicon-o-star', 'size-4') My Review</p>
        <div class="space-y-1 border-l border-violet-100 pl-2">
            <a href="{{ route('store.account.reviews', 'to-review') }}" class="{{ $linkClass }} {{ $routeName === 'store.account.reviews' && $routeFilter === 'to-review' ? $activeClass : $idleClass }}"><span>To Review</span><span class="text-xs">{{ $counts['toReview'] ?? 0 }}</span></a>
            <a href="{{ route('store.account.reviews', 'all') }}" class="{{ $linkClass }} {{ $routeName === 'store.account.reviews' && $routeFilter === 'all' ? $activeClass : $idleClass }}">All Review</a>
        </div>
    </div>

    <div>
        <p class="mb-1.5 flex items-center gap-2 px-3 text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">@svg('heroicon-o-arrow-path', 'size-4') Return &amp; Cancellation</p>
        <div class="space-y-1 border-l border-violet-100 pl-2">
            @foreach([['return', 'new', 'Return', null], ['return', 'all', 'All Return', $counts['returns'] ?? 0], ['cancellation', 'new', 'Cancel', null], ['cancellation', 'all', 'All Cancellation', $counts['cancellations'] ?? 0]] as [$type, $mode, $label, $count])
                <a href="{{ route('store.account.requests', [$type, $mode]) }}" class="{{ $linkClass }} {{ $routeName === 'store.account.requests' && $requestType === $type && $requestMode === $mode ? $activeClass : $idleClass }}"><span>{{ $label }}</span>@if($count !== null)<span class="text-xs">{{ $count }}</span>@endif</a>
            @endforeach
        </div>
    </div>

    <div>
        <p class="mb-1.5 flex items-center gap-2 px-3 text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">@svg('heroicon-o-cog-6-tooth', 'size-4') Manage My Account</p>
        <div class="space-y-1 border-l border-violet-100 pl-2">
            <a href="{{ route('store.account.profile') }}" class="{{ $linkClass }} {{ $routeName === 'store.account.profile' ? $activeClass : $idleClass }}">My Profile</a>
            <a href="{{ route('store.account.addresses') }}" class="{{ $linkClass }} {{ $routeName === 'store.account.addresses' ? $activeClass : $idleClass }}">Address Book</a>
            <a href="{{ route('store.account.payment-options') }}" class="{{ $linkClass }} {{ $routeName === 'store.account.payment-options' ? $activeClass : $idleClass }}">Payment Option</a>
        </div>
    </div>

    <form method="POST" action="{{ route('store.account.logout') }}" class="border-t border-slate-100 pt-3">
        @csrf
        <button class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-rose-600 hover:bg-rose-50">@svg('heroicon-o-arrow-right-start-on-rectangle', 'size-5') Log out</button>
    </form>
</div>
