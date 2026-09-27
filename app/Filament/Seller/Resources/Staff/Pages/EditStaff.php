<?php

namespace App\Filament\Seller\Resources\Staff\Pages;

use App\Enums\VendorMemberRole;
use App\Filament\Seller\Resources\Staff\StaffResource;
use App\Support\SellerAccess;
use Filament\Resources\Pages\EditRecord;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected string $memberRole = VendorMemberRole::Staff->value;

    protected string $membershipStatus = 'active';

    /** @var list<string> */
    protected array $vendorPermissions = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $pivot = $this->record->vendors()
            ->whereKey(SellerAccess::currentVendor()?->getKey() ?? 0)
            ->first()?->pivot;

        $permissions = $pivot?->permissions;
        $permissions = is_string($permissions) ? json_decode($permissions, true) : $permissions;

        $data['member_role'] = $pivot?->role ?? VendorMemberRole::Staff->value;
        $data['membership_status'] = $pivot?->status ?? 'active';
        $data['vendor_permissions'] = is_array($permissions) ? $permissions : [];

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->memberRole = $data['member_role'] ?? VendorMemberRole::Staff->value;
        $this->membershipStatus = $data['membership_status'] ?? 'active';
        $this->vendorPermissions = array_values($data['vendor_permissions'] ?? []);
        unset($data['member_role'], $data['membership_status'], $data['vendor_permissions']);

        return $data;
    }

    protected function afterSave(): void
    {
        SellerAccess::currentVendor()?->members()->updateExistingPivot($this->record->getKey(), [
            'role' => $this->memberRole,
            'status' => $this->membershipStatus,
            'permissions' => json_encode($this->vendorPermissions),
        ]);
    }
}
