@if($relatedProducts->isNotEmpty())
    <section data-landing-related-products class="storefront-shell pb-14 pt-8">
        <h2 class="text-2xl font-black text-slate-950">{{ __('Related Products') }}</h2>
        <div data-product-grid class="storefront-product-grid mt-7 grid-cols-2 sm:grid-cols-3 xl:grid-cols-6">
            @foreach($relatedProducts as $card)
                @include('storefront.components.product-card', ['card' => $card])
            @endforeach
        </div>
    </section>
@endif
