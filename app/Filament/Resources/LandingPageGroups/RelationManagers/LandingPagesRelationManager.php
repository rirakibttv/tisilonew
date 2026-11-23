<?php

namespace App\Filament\Resources\LandingPageGroups\RelationManagers;

use App\Filament\Resources\LandingPages\Schemas\LandingPageForm;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;

class LandingPagesRelationManager extends RelationManager
{
    protected static string $relationship = 'landingPages';

    protected static bool $isLazy = false;

    protected static ?string $title = 'Advertisements in this Group';

    protected static ?string $modelLabel = 'Advertisement';

    protected static ?string $pluralModelLabel = 'Advertisements';

    public function form(Schema $schema): Schema
    {
        return LandingPageForm::configure($schema, showGroup: false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('hero_image')->label('Hero')->disk('public')->square(),
                TextColumn::make('name')->label('Advertisement')->description(fn ($record): string => '/offer/'.$record->slug)->searchable()->sortable(),
                TextColumn::make('products_count')->label('Products')->counts('products')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),
                TextColumn::make('published_at')->label('Published')->dateTime('d M Y, h:i A')->placeholder('Draft')->sortable(),
            ])
            ->headerActions([
                CreateAction::make()->label('Add Advertisement'),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn ($record): string => URL::temporarySignedRoute('store.landing.preview', now()->addHour(), ['landingPage' => $record]))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
