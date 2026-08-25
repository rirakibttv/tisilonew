<?php

namespace App\Filament\Resources\VendorWarehouses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorWarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Warehouse')
                ->columns(2)
                ->schema([
                    Select::make('vendor_id')
                        ->relationship('vendor', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('code')
                        ->required()
                        ->maxLength(60),

                    TextInput::make('contact_name')
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(32),

                    Toggle::make('is_default')
                        ->label('Default Warehouse'),

                    Toggle::make('status')
                        ->label('Active')
                        ->default(true),
                ]),

            Section::make('Address')
                ->columns(2)
                ->schema([
                    TextInput::make('address_line_1')
                        ->label('Address')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('address_line_2')
                        ->label('Address Details')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('district')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('upazila')
                        ->maxLength(100),

                    TextInput::make('postal_code')
                        ->maxLength(20),
                ]),
        ]);
    }
}
