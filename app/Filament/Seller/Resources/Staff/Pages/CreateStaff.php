<?php

namespace App\Filament\Seller\Resources\Staff\Pages;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VendorMemberRole;
use App\Filament\Seller\Resources\Staff\StaffResource;
use App\Support\SellerAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected string $memberRole = VendorMemberRole::Staff->value;

    protected string $membershipStatus = 'active';

    /** @var list<string> */
    protected array $vendorPermissions = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->memberRole = $data['member_role'] ?? VendorMemberRole::Staff->value;
        $this->membershipStatus = $data['membership_status'] ?? 'active';
        $this->vendorPermissions = array_values($data['vendor_permissions'] ?? []);
        unset($data['member_role'], $data['membership_status'], $data['vendor_permissions']);

        $data['role'] = UserRole::VendorStaff->value;
        $data['status'] = UserStatus::Active->value;

        return $data;
    }

    protected function afterCreate(): void
    {
        SellerAccess::currentVendor()?->members()->syncWithoutDetaching([
            $this->record->getKey() => [
                'role' => $this->memberRole,
                'status' => $this->membershipStatus,
                'permissions' => json_encode($this->vendorPermissions),
                'joined_at' => now(),
            ],
        ]);
    }
}
