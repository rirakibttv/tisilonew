<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account Information')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(32)
                        ->unique(ignoreRecord: true),

                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn ($state): bool => filled($state)),

                    Select::make('role')
                        ->options(UserRole::options())
                        ->default(UserRole::Customer->value)
                        ->required(),

                    Select::make('access_role_id')
                        ->label('Access Role')
                        ->relationship('accessRole', 'name', modifyQueryUsing: fn ($query) => $query->where('status', true))
                        ->searchable()
                        ->preload()
                        ->helperText('Optional granular permission role. The account type above still controls panel login.'),

                    Select::make('status')
                        ->options(UserStatus::options())
                        ->default(UserStatus::Active->value)
                        ->required(),
                ]),
        ]);
    }
}
