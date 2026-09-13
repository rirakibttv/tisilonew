<?php

namespace App\Filament\Resources\PopupOffers;

use App\Filament\Resources\PopupOffers\Pages\CreatePopupOffer;
use App\Filament\Resources\PopupOffers\Pages\EditPopupOffer;
use App\Filament\Resources\PopupOffers\Pages\ListPopupOffers;
use App\Models\PopupOffer;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PopupOfferResource extends Resource
{
    protected static ?string $model = PopupOffer::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $modelLabel = 'Popup Offer';

    protected static ?string $pluralModelLabel = 'Popup Offers';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'xl' => 2])
                ->schema([
                    Section::make('Popup Image')
                        ->description('Upload the main artwork and optionally make the complete image clickable.')
                        ->schema([
                            FileUpload::make('image')
                                ->label('Popup Image')
                                ->image()
                                ->imageEditor()
                                ->disk('public')
                                ->directory('popup-offers')
                                ->visibility('public')
                                ->required(),
                            TextInput::make('link_url')
                                ->label('Link (Image Click)')
                                ->placeholder('https://...')
                                ->url()
                                ->maxLength(1000),
                        ]),

                    Grid::make(1)->schema([
                        Section::make('Status & Schedule')
                            ->description('Only one popup can remain active. Activating this one automatically disables the previous popup.')
                            ->columns(2)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Popup Active')
                                    ->default(false),
                                TextInput::make('display_delay_seconds')
                                    ->label('Show After (seconds)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(60)
                                    ->default(1)
                                    ->required(),
                                DateTimePicker::make('starts_at')
                                    ->label('Starts At')
                                    ->helperText('Leave empty to start immediately.'),
                                DateTimePicker::make('ends_at')
                                    ->label('Ends At')
                                    ->helperText('Leave empty for no expiry.')
                                    ->after('starts_at'),
                            ]),

                        Section::make('Text / Button (Optional)')
                            ->description('Leave these fields empty to display only the uploaded artwork.')
                            ->columns(2)
                            ->schema([
                                TextInput::make('title')->maxLength(255),
                                TextInput::make('button_text')->label('Button Text')->maxLength(120),
                                Textarea::make('description')->rows(4)->maxLength(1000)->columnSpanFull(),
                                TextInput::make('footer_text')->label('Footer Text')->maxLength(255)->columnSpanFull(),
                            ]),
                    ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->label('Artwork')->disk('public')->square(),
                TextColumn::make('title')
                    ->label('Popup')
                    ->placeholder('Image-only popup')
                    ->description(fn (PopupOffer $record): string => 'Campaign '.$record->uuid)
                    ->searchable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('starts_at')->label('Starts')->dateTime('d M Y, h:i A')->placeholder('Immediately'),
                TextColumn::make('ends_at')->label('Ends')->dateTime('d M Y, h:i A')->placeholder('No expiry'),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPopupOffers::route('/'),
            'create' => CreatePopupOffer::route('/create'),
            'edit' => EditPopupOffer::route('/{record}/edit'),
        ];
    }
}
