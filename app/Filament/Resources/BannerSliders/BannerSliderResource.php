<?php

namespace App\Filament\Resources\BannerSliders;

use App\Filament\Resources\BannerSliders\Pages\CreateBannerSlider;
use App\Filament\Resources\BannerSliders\Pages\EditBannerSlider;
use App\Filament\Resources\BannerSliders\Pages\ListBannerSliders;
use App\Models\BannerSlider;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BannerSliderResource extends Resource
{
    protected static ?string $model = BannerSlider::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $modelLabel = 'Slider';

    protected static ?string $pluralModelLabel = 'Banner & Slider';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Slider Artwork')
                ->description('Recommended image size: 1060 × 395 pixels. This slider has one fixed placement on the storefront homepage.')
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
                        ->default(fn (): int => ((int) BannerSlider::query()->max('sort_order')) + 1)
                        ->required(),
                    Toggle::make('is_active')
                        ->label('Active Mode')
                        ->helperText('Visible on website')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Slider')->searchable()->sortable(),
                ImageColumn::make('image')->label('Image')->disk('public')->width(150)->height(56),
                TextColumn::make('destination_url')->label('Destination URL')->placeholder('No link')->limit(55),
                TextColumn::make('sort_order')->label('Order')->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean()->sortable(),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
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

    public static function getPages(): array
    {
        return [
            'index' => ListBannerSliders::route('/'),
            'create' => CreateBannerSlider::route('/create'),
            'edit' => EditBannerSlider::route('/{record}/edit'),
        ];
    }
}
