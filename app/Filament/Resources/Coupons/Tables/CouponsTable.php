<?php

namespace App\Filament\Resources\Coupons\Tables;

use App\Models\Coupon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Coupon Code')
                    ->badge()
                    ->copyable()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Campaign')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('discount')
                    ->label('Discount')
                    ->state(fn (Coupon $record): string => $record->discount_type === 'percentage'
                        ? number_format((float) $record->discount_value, 2).'%'
                        : '৳'.number_format((float) $record->discount_value, 2))
                    ->badge()
                    ->color('success'),
                TextColumn::make('minimum_order_amount')
                    ->label('Minimum Order')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('usage')
                    ->label('Used / Limit')
                    ->state(fn (Coupon $record): string => $record->used_count.' / '.($record->usage_limit ?? '∞')),
                TextColumn::make('schedule_status')
                    ->label('Status')
                    ->state(fn (Coupon $record): string => match (true) {
                        ! $record->is_active => 'Inactive',
                        $record->starts_at?->isFuture() => 'Scheduled',
                        $record->expires_at?->isPast() => 'Expired',
                        $record->usage_limit !== null && $record->used_count >= $record->usage_limit => 'Limit Reached',
                        default => 'Active',
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Scheduled' => 'info',
                        'Expired', 'Limit Reached' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y, h:i A')
                    ->placeholder('Immediately')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('d M Y, h:i A')
                    ->placeholder('No expiry')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Enabled')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Enabled')
                    ->options(['1' => 'Active', '0' => 'Inactive']),
                SelectFilter::make('discount_type')
                    ->label('Discount Type')
                    ->options(['fixed' => 'Fixed Amount', 'percentage' => 'Percentage']),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->defaultPaginationPageOption(50);
    }
}
