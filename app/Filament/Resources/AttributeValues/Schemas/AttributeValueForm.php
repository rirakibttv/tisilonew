<?php

namespace App\Filament\Resources\AttributeValues\Schemas;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class AttributeValueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Attribute Value Information')
                    ->columns(2)
                    ->schema([
                        Select::make('attribute_id')
                            ->label('Attribute')
                            ->options(
                                fn () => Attribute::query()
                                    ->where('status', true)
                                    ->orderBy('sort_order')
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),

                        TextInput::make('value')
                            ->label('Value')
                            ->placeholder('Example: Red, XL, Cotton')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                table: AttributeValue::class,
                                column: 'value',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                                    ->where('attribute_id', $get('attribute_id')),
                            )
                            ->validationMessages([
                                'unique' => 'এই Attribute-এর জন্য এই Value ইতোমধ্যে যোগ করা আছে। অন্য Value দিন।',
                            ])
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn ($state, callable $set) => $set('slug', Str::slug($state))
                            ),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                table: AttributeValue::class,
                                column: 'slug',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                                    ->where('attribute_id', $get('attribute_id')),
                            )
                            ->validationMessages([
                                'unique' => 'এই Attribute-এর জন্য এই Slug ইতোমধ্যে ব্যবহার হয়েছে। অন্য Value বা Slug দিন।',
                            ]),

                        ColorPicker::make('color_code')
                            ->label('Color Code')
                            ->visible(function (Get $get): bool {
                                $attributeId = $get('attribute_id');

                                if (blank($attributeId)) {
                                    return false;
                                }

                                return Attribute::query()
                                    ->whereKey($attributeId)
                                    ->where('type', 'color')
                                    ->exists();
                            }),

                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),

                        Toggle::make('status')
                            ->label('Active')
                            ->default(true),
                    ]),
            ]);
    }
}
