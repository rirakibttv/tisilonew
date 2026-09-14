<?php

namespace App\Filament\Resources\SliderGroups;

use App\Filament\Resources\SliderGroups\Pages\CreateSliderGroup;
use App\Filament\Resources\SliderGroups\Pages\EditSliderGroup;
use App\Filament\Resources\SliderGroups\Pages\ListSliderGroups;
use App\Filament\Resources\SliderGroups\RelationManagers\SlidesRelationManager;
use App\Models\SliderGroup;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SliderGroupResource extends Resource
{
    protected static ?string $model = SliderGroup::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $modelLabel = 'Master Slider';

    protected static ?string $pluralModelLabel = 'Slider Panel';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Master Slider')
                ->description('A master slider controls one homepage position. Add its individual slides from the Slides section below.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Slider Name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Select::make('placement')
                        ->label('Homepage Placement')
                        ->options(SliderGroup::placementOptions())
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Placement is selected once for the master slider, not for every slide.'),

                    TextInput::make('sort_order')
                        ->label('List Order')
                        ->numeric()
                        ->minValue(0)
                        ->default(fn (): int => ((int) SliderGroup::query()->max('sort_order')) + 1)
                        ->required(),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->helperText('Show this slider on the storefront.')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Master Slider')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('placement')
                    ->label('Homepage Position')
                    ->formatStateUsing(fn (string $state): string => SliderGroup::placementOptions()[$state] ?? Str::headline($state))
                    ->badge(),
                TextColumn::make('slides_count')
                    ->label('Slides')
                    ->counts('slides')
                    ->badge(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()->label('Manage Slides'),
                DeleteAction::make()
                    ->hidden(fn (SliderGroup $record): bool => $record->placement === SliderGroup::MAIN_PLACEMENT
                        || $record->slides()->exists()),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            SlidesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSliderGroups::route('/'),
            'create' => CreateSliderGroup::route('/create'),
            'edit' => EditSliderGroup::route('/{record}/edit'),
        ];
    }
}
