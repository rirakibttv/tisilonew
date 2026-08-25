<?php

namespace App\Filament\Resources\Vendors\Schemas;

use App\Enums\VendorStatus;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Vendor Identity')
                ->description('The legal owner and public storefront identity.')
                ->columns(2)
                ->schema([
                    Select::make('owner_id')
                        ->label('Account Owner')
                        ->relationship('owner', 'name')
                        ->getOptionLabelFromRecordUsing(
                            fn (User $record): string => "{$record->name} ({$record->email})",
                        )
                        ->searchable(['name', 'email', 'phone'])
                        ->preload()
                        ->required(),

                    Select::make('status')
                        ->options(VendorStatus::options())
                        ->default(VendorStatus::Pending->value)
                        ->required(),

                    TextInput::make('name')
                        ->label('Store Name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(
                            fn ($state, callable $set) => $set('slug', Str::slug($state)),
                        ),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    TextInput::make('legal_name')
                        ->label('Legal Business Name')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('commission_rate')
                        ->label('Marketplace Commission')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(0)
                        ->suffix('%')
                        ->required(),
                ]),

            Section::make('Contact')
                ->columns(2)
                ->schema([
                    TextInput::make('email')
                        ->email()
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(32),

                    TextInput::make('website')
                        ->url()
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),

            Section::make('Storefront')
                ->columns(2)
                ->schema([
                    FileUpload::make('logo')
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('vendors/logos')
                        ->visibility('public'),

                    FileUpload::make('banner')
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('vendors/banners')
                        ->visibility('public'),

                    RichEditor::make('description')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
