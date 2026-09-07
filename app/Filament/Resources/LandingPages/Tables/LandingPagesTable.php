<?php

namespace App\Filament\Resources\LandingPages\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;

class LandingPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('hero_image')->label('Hero')->disk('public')->square(),
                TextColumn::make('name')->label('Campaign')->description(fn ($record): string => '/offer/'.$record->slug)->searchable()->sortable(),
                TextColumn::make('header_title')->label('Header Title')->placeholder('Uses headline')->searchable()->toggleable(),
                TextColumn::make('products_count')->label('Products')->counts('products')->sortable(),
                TextColumn::make('orders_count')->label('Orders')->counts('orders')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),
                TextColumn::make('published_at')->label('Published At')->dateTime('d M Y, h:i A')->placeholder('Draft')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
            ])
            ->recordActions([
                Action::make('preview')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn ($record): string => URL::temporarySignedRoute('store.landing.preview', now()->addHour(), ['landingPage' => $record]))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
