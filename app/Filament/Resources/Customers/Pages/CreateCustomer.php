<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['role'] = UserRole::Customer->value;

        return $data;
    }
}
