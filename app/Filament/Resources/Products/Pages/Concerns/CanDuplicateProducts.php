<?php

namespace App\Filament\Resources\Products\Pages\Concerns;

use App\Filament\Resources\Products\ProductResource;
use App\Services\ProductDuplicator;
use Filament\Notifications\Notification;

trait CanDuplicateProducts
{
    public function duplicateProduct(int $productId): void
    {
        $product = ProductResource::getEloquentQuery()->findOrFail($productId);

        ProductResource::authorizeCreate();
        ProductResource::authorize('replicate', $product);

        $duplicate = app(ProductDuplicator::class)->duplicate($product);

        Notification::make()
            ->title('Product duplicated')
            ->body("{$duplicate->name} was created as a draft with its attributes and variations.")
            ->success()
            ->send();

        $this->resetTable();
    }
}
