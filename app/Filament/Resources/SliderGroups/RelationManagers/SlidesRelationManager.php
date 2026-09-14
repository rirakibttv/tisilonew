<?php

namespace App\Filament\Resources\SliderGroups\RelationManagers;

use App\Models\BannerSlider;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SlidesRelationManager extends RelationManager
{
    protected static string $relationship = 'slides';

    protected static bool $isLazy = false;

    protected static ?string $title = 'Slides';

    protected static ?string $modelLabel = 'Slide';

    protected static ?string $pluralModelLabel = 'Slides';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Slider Artwork')
                ->description('Recommended image size: 1060 × 395 pixels. Placement is inherited from the master slider.')
                ->schema([
                    FileUpload::make('image')
                        ->label('Slider Image')
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('banner-sliders')
                        ->visibility('public')
                        ->panelLayout('integrated')
                        ->panelAspectRatio('1060:395')
                        ->imagePreviewHeight('395')
                        ->required(),
                ]),

            Section::make('Link & Publication')
                ->columns(3)
                ->schema([
                    TextInput::make('destination_url')
                        ->label('Destination URL')
                        ->placeholder('#, /shop or https://...')
                        ->helperText('Use # for no destination, /shop for an internal page, or a complete https:// URL.')
                        ->maxLength(1000)
                        ->columnSpan(2),
                    TextInput::make('sort_order')
                        ->label('Sort Order')
                        ->numeric()
                        ->minValue(0)
                        ->default(fn (): int => ((int) BannerSlider::query()
                            ->where('slider_group_id', $this->getOwnerRecord()->getKey())
                            ->max('sort_order')) + 1)
                        ->required(),
                    Toggle::make('is_active')
                        ->label('Active Mode')
                        ->helperText('Visible on website')
                        ->default(true),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Slide')->sortable(),
                ImageColumn::make('image')->label('Image')->disk('public')->width(150)->height(56),
                TextColumn::make('destination_url')->label('Destination URL')->placeholder('No link')->limit(55),
                TextColumn::make('sort_order')->label('Order')->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean()->sortable(),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
            ])
            ->headerActions([
                CreateAction::make()->label('Add Slide'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}
