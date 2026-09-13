<?php

namespace App\Filament\Resources\ProductReviews\Pages;

use App\Filament\Resources\ProductReviews\ProductReviewResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductReviews extends ListRecords
{
    protected static string $resource = ProductReviewResource::class;

    protected static ?string $title = 'All Reviews';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Create Review')];
    }
}
