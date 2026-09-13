<?php

namespace App\Filament\Resources\ProductReviews;

use App\Filament\Resources\ProductReviews\Pages\CreateProductReview;
use App\Filament\Resources\ProductReviews\Pages\EditProductReview;
use App\Filament\Resources\ProductReviews\Pages\ListPendingProductReviews;
use App\Filament\Resources\ProductReviews\Pages\ListProductReviews;
use App\Models\ProductReview;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductReviewResource extends Resource
{
    protected static ?string $model = ProductReview::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $modelLabel = 'Review';

    protected static ?string $pluralModelLabel = 'Reviews';

    protected static ?string $recordTitleAttribute = 'reviewer_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Review Information')
                ->description('Admin-created reviews are recorded as manual reviews and are never marked as verified purchases.')
                ->columns(2)
                ->schema([
                    Select::make('product_id')
                        ->label('Product')
                        ->relationship('product', 'name', fn ($query) => $query->where('status', 'published'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),
                    TextInput::make('reviewer_name')
                        ->label('Reviewer Name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('reviewer_email')
                        ->label('Reviewer Email')
                        ->email()
                        ->maxLength(255),
                    Select::make('rating')
                        ->options([
                            5 => '5 — Excellent',
                            4 => '4 — Very good',
                            3 => '3 — Good',
                            2 => '2 — Fair',
                            1 => '1 — Poor',
                        ])
                        ->required(),
                    Select::make('status')
                        ->options(ProductReview::statusOptions())
                        ->default(ProductReview::STATUS_APPROVED)
                        ->required(),
                    TextInput::make('title')
                        ->label('Review Title')
                        ->maxLength(150)
                        ->columnSpanFull(),
                    Textarea::make('review')
                        ->required()
                        ->rows(5)
                        ->maxLength(3000)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->limit(42),
                TextColumn::make('reviewer_name')
                    ->label('Reviewer')
                    ->description(fn (ProductReview $record): ?string => $record->reviewer_email)
                    ->searchable(['reviewer_name', 'reviewer_email'])
                    ->sortable(),
                TextColumn::make('rating')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color('warning')
                    ->sortable(),
                IconColumn::make('is_verified_purchase')
                    ->label('Verified Purchase')
                    ->boolean(),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === ProductReview::SOURCE_ADMIN ? 'Admin' : 'Customer')
                    ->color(fn (string $state): string => $state === ProductReview::SOURCE_ADMIN ? 'gray' : 'info'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ProductReview::statusOptions()[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        ProductReview::STATUS_APPROVED => 'success',
                        ProductReview::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ProductReview::statusOptions()),
                SelectFilter::make('rating')->options([
                    5 => '5 Stars',
                    4 => '4 Stars',
                    3 => '3 Stars',
                    2 => '2 Stars',
                    1 => '1 Star',
                ]),
                SelectFilter::make('source')->options([
                    ProductReview::SOURCE_CUSTOMER => 'Customer',
                    ProductReview::SOURCE_ADMIN => 'Admin',
                ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ProductReview $record): bool => $record->status !== ProductReview::STATUS_APPROVED)
                    ->action(function (ProductReview $record): void {
                        $record->update(['status' => ProductReview::STATUS_APPROVED]);
                        Notification::make()->title('Review approved')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ProductReview $record): bool => $record->status !== ProductReview::STATUS_REJECTED)
                    ->action(function (ProductReview $record): void {
                        $record->update(['status' => ProductReview::STATUS_REJECTED]);
                        Notification::make()->title('Review rejected')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductReviews::route('/'),
            'pending' => ListPendingProductReviews::route('/pending'),
            'create' => CreateProductReview::route('/create'),
            'edit' => EditProductReview::route('/{record}/edit'),
        ];
    }
}
