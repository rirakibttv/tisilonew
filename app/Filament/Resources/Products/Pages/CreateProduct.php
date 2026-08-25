<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\AttributeValue;
use App\Models\ProductVariation;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\DB;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /*
    |--------------------------------------------------------------------------
    | Temporary Variant Data
    |--------------------------------------------------------------------------
    */

    protected array $pendingVariants = [];

    protected array $pendingAttributeIds = [];

    /*
    |--------------------------------------------------------------------------
    | Before Product Create
    |--------------------------------------------------------------------------
    */

    protected function mutateFormDataBeforeCreate(
        array $data
    ): array {
        $this->pendingVariants =
            $data['variants'] ?? [];

        /*
         * Product Attributes is a relationship field.
         * Keep a copy of its current form state for
         * variation validation / creation.
         */
        $this->pendingAttributeIds = collect(
            $this->data['attributes'] ?? []
        )
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        unset($data['variants']);

        if (
            ($data['product_type'] ?? null)
            === 'variable'
        ) {
            $this->validatePendingVariants();
        }

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate All Pending Variants Before Product Is Created
    |--------------------------------------------------------------------------
    */

    protected function validatePendingVariants(): void
    {
        if (empty($this->pendingAttributeIds)) {
            Notification::make()
                ->title('Variation Attributes Required')
                ->body(
                    'Please select at least one product attribute.'
                )
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
        }

        if (empty($this->pendingVariants)) {
            Notification::make()
                ->title('Product Variant Required')
                ->body(
                    'Please add at least one product variant.'
                )
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
        }

        $combinationKeys = [];
        $usedSkus = [];
        $usedBarcodes = [];
        $defaultCount = 0;

        foreach (
            $this->pendingVariants as $index => $variant
        ) {
            $variantNumber = $index + 1;

            $attributeValueIds = [];

            /*
            |--------------------------------------------------------------------------
            | Exactly One Value Per Product Attribute
            |--------------------------------------------------------------------------
            */

            foreach (
                $this->pendingAttributeIds as $attributeId
            ) {
                $field =
                    'attribute_'.$attributeId;

                $valueId = $variant[$field] ?? null;

                if (blank($valueId)) {
                    Notification::make()
                        ->title(
                            'Incomplete Variant #'
                            .$variantNumber
                        )
                        ->body(
                            'Please select one value from every product attribute.'
                        )
                        ->danger()
                        ->persistent()
                        ->send();

                    throw new Halt;
                }

                $value = AttributeValue::query()
                    ->whereKey((int) $valueId)
                    ->where(
                        'attribute_id',
                        $attributeId
                    )
                    ->where(
                        'status',
                        true
                    )
                    ->first();

                if (! $value) {
                    Notification::make()
                        ->title(
                            'Invalid Variant #'
                            .$variantNumber
                        )
                        ->body(
                            'One or more selected attribute values are invalid.'
                        )
                        ->danger()
                        ->persistent()
                        ->send();

                    throw new Halt;
                }

                $attributeValueIds[] =
                    (int) $value->id;
            }

            sort($attributeValueIds);

            /*
            |--------------------------------------------------------------------------
            | Duplicate Combination Check
            |--------------------------------------------------------------------------
            */

            $combinationKey = implode(
                '-',
                $attributeValueIds
            );

            if (
                in_array(
                    $combinationKey,
                    $combinationKeys,
                    true
                )
            ) {
                Notification::make()
                    ->title('Duplicate Variation')
                    ->body(
                        'The same attribute combination was added more than once.'
                    )
                    ->danger()
                    ->persistent()
                    ->send();

                throw new Halt;
            }

            $combinationKeys[] =
                $combinationKey;

            /*
            |--------------------------------------------------------------------------
            | SKU Validation
            |--------------------------------------------------------------------------
            */

            $sku = filled($variant['sku'] ?? null)
                ? trim((string) $variant['sku'])
                : null;

            if ($sku !== null) {
                if (
                    in_array(
                        mb_strtolower($sku),
                        $usedSkus,
                        true
                    )
                ) {
                    Notification::make()
                        ->title('Duplicate SKU')
                        ->body(
                            "The SKU [{$sku}] was used more than once."
                        )
                        ->danger()
                        ->persistent()
                        ->send();

                    throw new Halt;
                }

                if (
                    ProductVariation::query()
                        ->where('sku', $sku)
                        ->exists()
                ) {
                    Notification::make()
                        ->title('SKU Already Exists')
                        ->body(
                            "The SKU [{$sku}] is already being used by another variation."
                        )
                        ->danger()
                        ->persistent()
                        ->send();

                    throw new Halt;
                }

                $usedSkus[] =
                    mb_strtolower($sku);
            }

            /*
            |--------------------------------------------------------------------------
            | Barcode Validation
            |--------------------------------------------------------------------------
            */

            $barcode = filled(
                $variant['barcode'] ?? null
            )
                ? trim(
                    (string) $variant['barcode']
                )
                : null;

            if ($barcode !== null) {
                if (
                    in_array(
                        mb_strtolower($barcode),
                        $usedBarcodes,
                        true
                    )
                ) {
                    Notification::make()
                        ->title('Duplicate Barcode')
                        ->body(
                            "The barcode [{$barcode}] was used more than once."
                        )
                        ->danger()
                        ->persistent()
                        ->send();

                    throw new Halt;
                }

                if (
                    ProductVariation::query()
                        ->where(
                            'barcode',
                            $barcode
                        )
                        ->exists()
                ) {
                    Notification::make()
                        ->title(
                            'Barcode Already Exists'
                        )
                        ->body(
                            "The barcode [{$barcode}] is already being used by another variation."
                        )
                        ->danger()
                        ->persistent()
                        ->send();

                    throw new Halt;
                }

                $usedBarcodes[] =
                    mb_strtolower($barcode);
            }

            /*
            |--------------------------------------------------------------------------
            | Price Validation
            |--------------------------------------------------------------------------
            */

            $purchasePrice = filled(
                $variant['purchase_price'] ?? null
            )
                ? (float) $variant[
                    'purchase_price'
                ]
                : null;

            $regularPrice = filled(
                $variant['regular_price'] ?? null
            )
                ? (float) $variant[
                    'regular_price'
                ]
                : null;

            $salePrice = filled(
                $variant['sale_price'] ?? null
            )
                ? (float) $variant[
                    'sale_price'
                ]
                : null;

            if (
                $purchasePrice !== null
                && $purchasePrice < 0
            ) {
                $this->invalidPriceNotification(
                    $variantNumber
                );
            }

            if (
                $regularPrice === null
                || $regularPrice < 0
            ) {
                Notification::make()
                    ->title(
                        'Invalid Regular Price'
                    )
                    ->body(
                        "Variant #{$variantNumber} requires a valid Regular Price."
                    )
                    ->danger()
                    ->persistent()
                    ->send();

                throw new Halt;
            }

            if (
                $salePrice !== null
                && $salePrice < 0
            ) {
                $this->invalidPriceNotification(
                    $variantNumber
                );
            }

            if (
                $salePrice !== null
                && $salePrice > $regularPrice
            ) {
                Notification::make()
                    ->title(
                        'Invalid Sale Price'
                    )
                    ->body(
                        "Variant #{$variantNumber}: Sale Price cannot be greater than Regular Price."
                    )
                    ->danger()
                    ->persistent()
                    ->send();

                throw new Halt;
            }

            /*
            |--------------------------------------------------------------------------
            | Stock Validation
            |--------------------------------------------------------------------------
            */

            $stockQuantity = (int) (
                $variant['stock_quantity'] ?? 0
            );

            $lowStockThreshold = (int) (
                $variant[
                    'low_stock_threshold'
                ] ?? 0
            );

            if ($stockQuantity < 0) {
                Notification::make()
                    ->title(
                        'Invalid Stock Quantity'
                    )
                    ->body(
                        "Variant #{$variantNumber}: Stock Quantity cannot be negative."
                    )
                    ->danger()
                    ->persistent()
                    ->send();

                throw new Halt;
            }

            if ($lowStockThreshold < 0) {
                Notification::make()
                    ->title(
                        'Invalid Low Stock Threshold'
                    )
                    ->body(
                        "Variant #{$variantNumber}: Low Stock Threshold cannot be negative."
                    )
                    ->danger()
                    ->persistent()
                    ->send();

                throw new Halt;
            }

            /*
            |--------------------------------------------------------------------------
            | Default / Active Validation
            |--------------------------------------------------------------------------
            */

            $isDefault = (bool) (
                $variant['is_default'] ?? false
            );

            $isActive = (bool) (
                $variant['status'] ?? false
            );

            if ($isDefault) {
                $defaultCount++;

                if (! $isActive) {
                    Notification::make()
                        ->title(
                            'Invalid Default Variation'
                        )
                        ->body(
                            "Variant #{$variantNumber}: A default variation must be active."
                        )
                        ->danger()
                        ->persistent()
                        ->send();

                    throw new Halt;
                }
            }
        }

        if ($defaultCount > 1) {
            Notification::make()
                ->title(
                    'Multiple Default Variations'
                )
                ->body(
                    'Only one variation can be marked as Default.'
                )
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Price Notification Helper
    |--------------------------------------------------------------------------
    */

    protected function invalidPriceNotification(
        int $variantNumber
    ): never {
        Notification::make()
            ->title('Invalid Price')
            ->body(
                "Variant #{$variantNumber}: Prices cannot be negative."
            )
            ->danger()
            ->persistent()
            ->send();

        throw new Halt;
    }

    /*
    |--------------------------------------------------------------------------
    | After Product Created
    |--------------------------------------------------------------------------
    */

    protected function afterCreate(): void
    {
        if (
            $this->record->product_type
            !== 'variable'
        ) {
            return;
        }

        /*
         * Make absolutely sure the Product Attributes
         * relationship contains exactly what was selected
         * on the Create form.
         */
        $this->record
            ->attributes()
            ->sync(
                $this->pendingAttributeIds
            );

        if (empty($this->pendingVariants)) {
            return;
        }

        DB::transaction(
            function (): void {
                foreach (
                    $this->pendingVariants as $variantData
                ) {
                    $attributeValueIds = [];

                    /*
                    |--------------------------------------------------------------------------
                    | Collect Dynamic Attribute Values
                    |--------------------------------------------------------------------------
                    */

                    foreach (
                        $this->pendingAttributeIds as $attributeId
                    ) {
                        $field =
                            'attribute_'
                            .$attributeId;

                        $attributeValueIds[] =
                            (int) $variantData[
                                $field
                            ];

                        unset(
                            $variantData[$field]
                        );
                    }

                    sort($attributeValueIds);

                    /*
                    |--------------------------------------------------------------------------
                    | Normalize Empty Codes
                    |--------------------------------------------------------------------------
                    */

                    if (
                        blank(
                            $variantData['sku']
                            ?? null
                        )
                    ) {
                        $variantData['sku'] =
                            null;
                    }

                    if (
                        blank(
                            $variantData['barcode']
                            ?? null
                        )
                    ) {
                        $variantData['barcode'] =
                            null;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Normalize Stock Status
                    |--------------------------------------------------------------------------
                    */

                    $stockQuantity = (int) (
                        $variantData[
                            'stock_quantity'
                        ] ?? 0
                    );

                    $stockStatus =
                        $variantData[
                            'stock_status'
                        ]
                        ?? 'in_stock';

                    /*
                     * On Backorder is deliberately
                     * preserved as a manual option.
                     */
                    if (
                        $stockStatus
                        !== 'on_backorder'
                    ) {
                        $stockStatus =
                            $stockQuantity > 0
                                ? 'in_stock'
                                : 'out_of_stock';
                    }

                    $variantData[
                        'stock_status'
                    ] = $stockStatus;

                    /*
                    |--------------------------------------------------------------------------
                    | Create Variation
                    |--------------------------------------------------------------------------
                    */

                    /** @var ProductVariation $variation */
                    $variation = $this->record
                        ->variations()
                        ->create(
                            $variantData
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Sync Attribute Values
                    |--------------------------------------------------------------------------
                    */

                    $variation
                        ->attributeValues()
                        ->sync(
                            $attributeValueIds
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Single Default Protection
                    |--------------------------------------------------------------------------
                    */

                    if (
                        (bool) $variation
                            ->is_default
                    ) {
                        $this->record
                            ->variations()
                            ->where(
                                'id',
                                '!=',
                                $variation
                                    ->getKey()
                            )
                            ->update([
                                'is_default' => false,
                            ]);
                    }
                }
            }
        );

        $this->pendingVariants = [];
        $this->pendingAttributeIds = [];
    }
}
