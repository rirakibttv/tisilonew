<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /*
        |--------------------------------------------------------------------------
        | Prevent Variable Product → Simple Product Conversion
        |--------------------------------------------------------------------------
        |
        | A variable product cannot be converted to a simple product while
        | it still has existing variations.
        |
        */

        if (
            $this->record->product_type === 'variable'
            && ($data['product_type'] ?? null) === 'simple'
            && $this->record->variations()->exists()
        ) {
            Notification::make()
                ->title('Cannot Change Product Type')
                ->body(
                    'This product still has variations. Delete all existing variations before changing it to a Simple Product.'
                )
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
        }

        /*
        |--------------------------------------------------------------------------
        | Variable Product Attribute Protection
        |--------------------------------------------------------------------------
        |
        | If this product already has variations, an attribute that is being
        | used by those variations cannot be removed from the product.
        |
        */

        if (
            ($data['product_type'] ?? null) !== 'variable'
            || ! $this->record->variations()->exists()
        ) {
            return $data;
        }

        /*
        |--------------------------------------------------------------------------
        | Selected Product Attributes
        |--------------------------------------------------------------------------
        |
        | "attributes" is a relationship field. Depending on Filament's
        | relationship save lifecycle it may not always exist directly
        | inside $data, so the current form state is used as fallback.
        |
        */

        $selectedAttributeIds = collect(
            $data['attributes']
                ?? $this->data['attributes']
                ?? []
        )
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Attributes Currently Used By Existing Variations
        |--------------------------------------------------------------------------
        */

        $usedAttributeIds = $this->record
            ->variations()
            ->with('attributeValues:id,attribute_id')
            ->get()
            ->flatMap(
                fn ($variation) => $variation->attributeValues
                    ->pluck('attribute_id')
            )
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Detect Removed Attributes
        |--------------------------------------------------------------------------
        */

        $removedUsedAttributeIds = array_values(
            array_diff(
                $usedAttributeIds,
                $selectedAttributeIds
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Stop Save If A Used Attribute Was Removed
        |--------------------------------------------------------------------------
        */

        if (! empty($removedUsedAttributeIds)) {
            Notification::make()
                ->title('Cannot Remove Variation Attribute')
                ->body(
                    'One or more selected attributes are already used by existing product variations. Delete or update those variations first.'
                )
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
        }

        return $data;
    }
}
