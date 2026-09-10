@php
    use App\Filament\Resources\Products\ProductResource;

    $product = $getRecord();
@endphp

<div class="flex min-w-20 flex-col items-start gap-1 whitespace-nowrap text-sm leading-tight">
    @if ($product->status === 'published')
        <a
            href="{{ route('store.products.show', $product) }}"
            target="_blank"
            rel="noopener noreferrer"
            class="font-medium text-gray-950 hover:text-primary-600 hover:underline dark:text-white"
        >
            View
        </a>
    @else
        <span class="cursor-not-allowed text-gray-400" title="Publish this product to view it on the storefront">
            View
        </span>
    @endif

    <a
        href="{{ ProductResource::getUrl('edit', ['record' => $product]) }}"
        class="font-medium text-primary-600 hover:text-primary-500 hover:underline"
    >
        Edit
    </a>

    <button
        type="button"
        wire:click="duplicateProduct({{ $product->getKey() }})"
        wire:confirm="Duplicate this product with its attributes and variations?"
        wire:loading.attr="disabled"
        wire:target="duplicateProduct({{ $product->getKey() }})"
        class="font-medium text-gray-950 hover:text-primary-600 hover:underline disabled:cursor-wait disabled:opacity-50 dark:text-white"
    >
        Duplicate
    </button>
</div>
