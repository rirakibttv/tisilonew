<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
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

                TextColumn::make('shippingClass.name')
                    ->label('Shipping Class')
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('regular_price')
                    ->label('Regular Price')
                    ->money('BDT')
                    ->sortable(),

                TextColumn::make('sale_price')
                    ->label('Sale Price')
                    ->money('BDT')
                    ->sortable(),

                TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->sortable(),

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
}
