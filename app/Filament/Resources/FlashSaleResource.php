<?php

namespace App\Filament\Resources;

use App\Models\FlashSale;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FlashSaleResource extends Resource
{
    protected static ?string $model = FlashSale::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $modelLabel = 'Flash Sale';

    protected static ?string $pluralModelLabel = 'Flash Sales';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Flash Sale Schedule')
                ->description('Set the campaign time first, then add products and Flash Sale prices from the list below.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Flash Sale Name')
                        ->required()
                        ->maxLength(255),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                    DateTimePicker::make('starts_at')
                        ->label('Starts At')
                        ->seconds(false)
                        ->default(now())
                        ->required(),
                    DateTimePicker::make('ends_at')
                        ->label('Ends At')
                        ->seconds(false)
                        ->after('starts_at')
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Flash Sale')->searchable()->sortable(),
                TextColumn::make('items_count')->label('Products')->counts('items')->badge(),
                TextColumn::make('starts_at')->label('Starts')->dateTime('d M Y, h:i A')->sortable(),
                TextColumn::make('ends_at')->label('Ends')->dateTime('d M Y, h:i A')->sortable(),
                TextColumn::make('live_status')
                    ->label('Schedule Status')
                    ->state(fn (FlashSale $record): string => match (true) {
                        ! $record->is_active => 'Inactive',
                        $record->starts_at->isFuture() => 'Scheduled',
                        $record->ends_at->isPast() => 'Expired',
                        default => 'Live',
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Live' => 'success',
                        'Scheduled' => 'info',
                        'Expired' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->label('Manage'),
                DeleteAction::make(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->defaultPaginationPageOption(50);
    }

    public static function getRelations(): array
    {
        return [FlashSaleItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlashSales::route('/'),
            'create' => CreateFlashSale::route('/create'),
            'edit' => EditFlashSale::route('/{record}/edit'),
        ];
    }
}
