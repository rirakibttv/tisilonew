<?php

namespace App\Filament\Resources\ProductReviews\Pages;

use App\Filament\Resources\ProductReviews\ProductReviewResource;
use App\Models\ProductReview;
use Filament\Resources\Pages\CreateRecord;

class CreateProductReview extends CreateRecord
{
    protected static string $resource = ProductReviewResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = null;
        $data['order_item_id'] = null;
        $data['created_by'] = auth()->id();
        $data['source'] = ProductReview::SOURCE_ADMIN;
        $data['is_verified_purchase'] = false;

        return $data;
    }
}
