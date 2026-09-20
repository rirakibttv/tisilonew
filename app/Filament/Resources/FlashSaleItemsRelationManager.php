<?php

namespace App\Filament\Resources;

use App\Models\FlashSaleItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class FlashSaleItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static bool $isLazy = false;

    protected static ?string $title = 'Flash Sale Product & Price List';

    protected static ?string $modelLabel = 'Flash Sale Product';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Product & Flash Price')
                ->columns(2)
                ->schema([
                    Select::make('product_id')
                        ->label('Product')
                        ->relationship(
                            name: 'product',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn ($query) => $query->where('status', 'published')->orderBy('name'),
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->unique(
                            table: FlashSaleItem::class,
                            column: 'product_id',
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule): Unique => $rule
                                ->where('flash_sale_id', $this->getOwnerRecord()->getKey()),
                        )
                        ->validationMessages([
                            'unique' => 'এই পণ্যটি ইতোমধ্যে এই Flash Sale-এ যোগ করা হয়েছে।',
                        ]),

                    TextInput::make('flash_price')
                        ->label('Flash Sale Price')
                        ->prefix('৳')
                        ->numeric()
                        ->minValue(0.01)
                        ->required(),

                    TextInput::make('sort_order')
                        ->label('Sort Order')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product.name')
            ->columns([
                ImageColumn::make('product.featured_image')
                    ->label('Image')
                    ->disk('public')
                    ->square(),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('product.regular_price')
                    ->label('Regular Price')
                    ->money('BDT')
                    ->placeholder('Variation price'),
                TextColumn::make('flash_price')
                    ->label('Flash Price')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('Add Flash Sale Product'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->defaultPaginationPageOption(50);
    }
}
