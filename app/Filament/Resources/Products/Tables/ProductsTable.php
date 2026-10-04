<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use App\Models\ProductVariation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    private const PRODUCT_NAME_LINE_LENGTH = 51;

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->addSelect([
                'minimum_variation_price' => self::variationPriceAggregate('MIN'),
                'maximum_variation_price' => self::variationPriceAggregate('MAX'),
            ]))
            ->columns([
                TextColumn::make('id')
                    ->label('SL')
                    ->sortable()
                    ->alignCenter(),

                ImageColumn::make('featured_image')
                    ->label('Image')
                    ->disk('public')
                    ->square(),

                ViewColumn::make('product_actions')
                    ->label('Action')
                    ->view('filament.tables.columns.product-actions'),

                TextColumn::make('name')
                    ->label('Product')
                    ->state(fn (Product $record): array => self::productNameLines($record->name))
                    ->listWithLineBreaks()
                    ->width(360)
                    ->tooltip(fn (Product $record): ?string => mb_strlen($record->name) > (self::PRODUCT_NAME_LINE_LENGTH * 2)
                        ? $record->name
                        : null)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('product_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'simple' => 'Simple',
                        'variable' => 'Variable',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sale_price')
                    ->label('Sale Price')
                    ->state(fn (Product $record): array => self::salePriceLines($record))
                    ->listWithLineBreaks(),

                TextColumn::make('stock_status')
                    ->label('Stock Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in_stock' => 'In Stock',
                        'out_of_stock' => 'Out of Stock',
                        'on_backorder' => 'On Backorder',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'pending' => 'Pending',
                        'published' => 'Published',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                IconColumn::make('featured')
                    ->label('Featured')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product_type')
                    ->label('Product Type')
                    ->options([
                        'simple' => 'Simple Product',
                        'variable' => 'Variable Product',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'pending' => 'Pending',
                        'published' => 'Published',
                    ]),

                SelectFilter::make('stock_status')
                    ->label('Stock Status')
                    ->options([
                        'in_stock' => 'In Stock',
                        'out_of_stock' => 'Out of Stock',
                        'on_backorder' => 'On Backorder',
                    ]),

                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name'),

                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),

                SelectFilter::make('shipping_class_id')
                    ->label('Shipping Class')
                    ->relationship('shippingClass', 'name'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }

    private static function variationPriceAggregate(string $aggregate): Builder
    {
        return ProductVariation::query()
            ->selectRaw("{$aggregate}(COALESCE(NULLIF(sale_price, 0), regular_price))")
            ->whereColumn('product_variations.product_id', 'products.id')
            ->where('status', true);
    }

    /** @return array<int, string> */
    private static function productNameLines(string $name): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        $maximumLength = self::PRODUCT_NAME_LINE_LENGTH * 2;
        $name = mb_substr($name, 0, $maximumLength);

        return collect([0, self::PRODUCT_NAME_LINE_LENGTH])
            ->map(fn (int $offset): string => trim(mb_substr($name, $offset, self::PRODUCT_NAME_LINE_LENGTH)))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private static function salePriceLines(Product $product): array
    {
        if ($product->product_type !== 'variable') {
            return [self::formatPrice($product->sale_price)];
        }

        $minimumPrice = $product->getAttribute('minimum_variation_price');
        $maximumPrice = $product->getAttribute('maximum_variation_price');

        if (! is_numeric($minimumPrice) && ! is_numeric($maximumPrice)) {
            return ['—'];
        }

        $minimumPrice = is_numeric($minimumPrice) ? $minimumPrice : $maximumPrice;
        $maximumPrice = is_numeric($maximumPrice) ? $maximumPrice : $minimumPrice;

        return [
            'Min: '.self::formatPrice($minimumPrice),
            'Max: '.self::formatPrice($maximumPrice),
        ];
    }

    private static function formatPrice(mixed $price): string
    {
        return is_numeric($price)
            ? 'BDT '.number_format((float) $price, 2)
            : '—';
    }
}
