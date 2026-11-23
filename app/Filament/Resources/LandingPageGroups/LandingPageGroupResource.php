<?php

namespace App\Filament\Resources\LandingPageGroups;

use App\Filament\Resources\LandingPageGroups\Pages\CreateLandingPageGroup;
use App\Filament\Resources\LandingPageGroups\Pages\EditLandingPageGroup;
use App\Filament\Resources\LandingPageGroups\Pages\ListLandingPageGroups;
use App\Models\LandingPageGroup;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class LandingPageGroupResource extends Resource
{
    protected static ?string $model = LandingPageGroup::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;
    protected static ?string $modelLabel = 'Landing Page Group';
    protected static ?string $pluralModelLabel = 'Landing Page Groups';
    protected static ?string $recordTitleAttribute = 'name';

    public static function getPages(): array
    {
        return [
            'index' => ListLandingPageGroups::route('/'),
            'create' => CreateLandingPageGroup::route('/create'),
            'edit' => EditLandingPageGroup::route('/{record}/edit'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Landing Page Group')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255)->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('slug')->badge()->searchable(),
            TextColumn::make('landing_pages_count')->label('Landing Pages')->counts('landingPages')->sortable(),
            TextColumn::make('updated_at')->since()->sortable(),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make(),
        ])->toolbarActions([
            BulkActionGroup::make([DeleteBulkAction::make()]),
        ])->defaultSort('name');
    }
}
