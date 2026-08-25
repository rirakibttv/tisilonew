<?php

namespace App\Filament\Resources\AttributeValues\Pages;

use App\Filament\Resources\AttributeValues\AttributeValueResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class CreateAttributeValue extends CreateRecord
{
    protected static string $resource = AttributeValueResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'data.value' => 'এই Attribute-এর জন্য এই Value ইতোমধ্যে যোগ করা আছে। অন্য Value দিন।',
            ]);
        }
    }
}
