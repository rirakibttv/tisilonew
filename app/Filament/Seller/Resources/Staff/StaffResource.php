<?php

namespace App\Filament\Seller\Resources\Staff;

use App\Enums\VendorMemberRole;
use App\Filament\Seller\Resources\Staff\Pages\CreateStaff;
use App\Filament\Seller\Resources\Staff\Pages\EditStaff;
use App\Filament\Seller\Resources\Staff\Pages\ListStaff;
use App\Models\User;
use App\Support\SellerAccess;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Store Management';

    protected static ?string $navigationLabel = 'Staff & Permissions';

    protected static ?string $modelLabel = 'Staff Member';

    protected static ?string $pluralModelLabel = 'Staff & Permissions';

    protected static ?string $slug = 'staff';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        $roles = collect(VendorMemberRole::cases())
            ->reject(fn (VendorMemberRole $role): bool => $role === VendorMemberRole::Owner)
            ->mapWithKeys(fn (VendorMemberRole $role): array => [$role->value => $role->label()])
            ->all();

        return $schema->components([
            Section::make('Staff Account')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                    TextInput::make('phone')->tel()->maxLength(32)->unique(ignoreRecord: true),
                    TextInput::make('password')
                        ->password()->revealable()->minLength(8)
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn ($state): bool => filled($state)),
                    Select::make('member_role')
                        ->label('Staff Role')
                        ->options($roles)
                        ->default(VendorMemberRole::Staff->value)
                        ->required(),
                    Select::make('membership_status')
                        ->label('Access Status')
                        ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                        ->default('active')
                        ->required(),
                ]),
            Section::make('Additional Permissions')
                ->description('নির্বাচিত role-এর default permissions-এর সাথে এখানে দেওয়া permission যোগ হবে।')
                ->schema([
                    CheckboxList::make('vendor_permissions')
                        ->hiddenLabel()
                        ->options(SellerAccess::permissionOptions())
                        ->columns(2)
                        ->bulkToggleable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->placeholder('—'),
                TextColumn::make('seller_role')
                    ->label('Role')
                    ->badge()
                    ->getStateUsing(function (User $record): string {
                        $role = $record->vendors()
                            ->whereKey(SellerAccess::currentVendor()?->getKey() ?? 0)
                            ->first()?->pivot?->role;

                        return VendorMemberRole::tryFrom((string) $role)?->label() ?? 'Staff';
                    }),
                TextColumn::make('seller_status')
                    ->label('Access')
                    ->badge()
                    ->getStateUsing(fn (User $record): string => (string) ($record->vendors()
                        ->whereKey(SellerAccess::currentVendor()?->getKey() ?? 0)
                        ->first()?->pivot?->status ?? 'inactive'))
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger'),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (User $record): bool => static::canEdit($record)),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $vendorId = SellerAccess::currentVendor()?->getKey() ?? 0;

        return parent::getEloquentQuery()
            ->where('role', 'vendor_staff')
            ->whereHas('vendors', fn (Builder $query): Builder => $query->where('vendors.id', $vendorId));
    }

    public static function canViewAny(): bool
    {
        return SellerAccess::can(SellerAccess::STAFF_VIEW);
    }

    public static function canCreate(): bool
    {
        return SellerAccess::can(SellerAccess::STAFF_MANAGE);
    }

    public static function canEdit(Model $record): bool
    {
        $vendorId = SellerAccess::currentVendor()?->getKey() ?? 0;

        return SellerAccess::can(SellerAccess::STAFF_MANAGE)
            && $record->vendors()->whereKey($vendorId)->exists();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
            'create' => CreateStaff::route('/create'),
            'edit' => EditStaff::route('/{record}/edit'),
        ];
    }
}
