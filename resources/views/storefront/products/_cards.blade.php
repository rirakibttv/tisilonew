@foreach ($products as $card)
    @include('storefront.components.product-card', ['card' => $card])
@endforeach
