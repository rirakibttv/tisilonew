<?php

namespace App\Filament\Resources\Attributes;

use App\Models\AttributeValue;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class ValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    protected static ?string $title = 'Attribute Values';

    protected static ?string $modelLabel = 'Attribute Value';

    protected static ?string $pluralModelLabel = 'Attribute Values';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Attribute Value Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('value')
                            ->label('Value')
                            ->placeholder('Example: Red, XL, Cotton')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                table: AttributeValue::class,
                                column: 'value',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule): Unique => $rule
                                    ->where('attribute_id', $this->getOwnerRecord()->getKey()),
                            )
                            ->validationMessages([
                                'unique' => 'এই Attribute-এর জন্য এই Value ইতোমধ্যে যোগ করা আছে। অন্য Value দিন।',
                            ])
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn ($state, callable $set) => $set('slug', Str::slug((string) $state))
                            ),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                table: AttributeValue::class,
                                column: 'slug',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule): Unique => $rule
                                    ->where('attribute_id', $this->getOwnerRecord()->getKey()),
                            )
                            ->validationMessages([
                                'unique' => 'এই Attribute-এর জন্য এই Slug ইতোমধ্যে ব্যবহার হয়েছে। অন্য Value বা Slug দিন।',
                            ]),

                        ColorPicker::make('color_code')
                            ->label('Color Code')
                            ->visible(fn (): bool => $this->getOwnerRecord()->type === 'color'),

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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('value')
            ->columns([
                TextColumn::make('value')
                    ->label('Value')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),

                ColorColumn::make('color_code')
                    ->label('Color')
                    ->visible(fn (): bool => $this->getOwnerRecord()->type === 'color'),

                TextColumn::make('sort_order')
                    ->label('Sort Order')
                    ->sortable(),

                IconColumn::make('status')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Attribute Value'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }
}
