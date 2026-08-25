<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Enums\UserRole;
use App\Enums\VendorMemberRole;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\Vendor;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVendor extends EditRecord
{
    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        /** @var Vendor $vendor */
        $vendor = $this->record;

        $vendor->members()->syncWithoutDetaching([
            $vendor->owner_id => [
                'role' => VendorMemberRole::Owner->value,
                'status' => 'active',
                'joined_at' => now(),
            ],
        ]);

        if (! $vendor->owner->role->canAccessAdminPanel()) {
            $vendor->owner()->update([
                'role' => UserRole::VendorOwner,
            ]);
        }
    }
}
