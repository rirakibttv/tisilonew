<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Category Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn ($state, callable $set) => $set('slug', Str::slug($state))
                            ),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                table: Category::class,
                                column: 'slug',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule): Unique => $rule->whereNull('parent_id'),
                            )
                            ->validationMessages([
                                'unique' => 'এই Main Category slug ইতোমধ্যে ব্যবহার হয়েছে। অন্য slug দিন।',
                            ]),

                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Toggle::make('status')
                            ->label('Active')
                            ->default(true),

                        Toggle::make('show_on_homepage')
                            ->label('Homepage')
                            ->helperText('Enable this category to show its own product flow on the homepage.')
                            ->default(false),

                        FileUpload::make('image')
                            ->label('Category Image')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('categories')
                            ->visibility('public'),
                    ]),

                Section::make('Description')
                    ->schema([
                        RichEditor::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ]),

            ]);
    }
}
