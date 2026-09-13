<?php

namespace App\Filament\Resources\ProductReviews\Pages;

use App\Filament\Resources\ProductReviews\ProductReviewResource;
use App\Models\ProductReview;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPendingProductReviews extends ListRecords
{
    protected static string $resource = ProductReviewResource::class;

    protected static ?string $title = 'Pending Reviews';

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->where('status', ProductReview::STATUS_PENDING);
    }
}
