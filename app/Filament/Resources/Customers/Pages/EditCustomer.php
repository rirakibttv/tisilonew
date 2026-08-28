<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['role'] = UserRole::Customer->value;

        return $data;
    }
}
