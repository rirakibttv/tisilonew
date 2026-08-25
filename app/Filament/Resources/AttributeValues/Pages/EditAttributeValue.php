<?php

namespace App\Filament\Resources\AttributeValues\Pages;

use App\Filament\Resources\AttributeValues\AttributeValueResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class EditAttributeValue extends EditRecord
{
    protected static string $resource = AttributeValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'data.value' => 'এই Attribute-এর জন্য এই Value ইতোমধ্যে যোগ করা আছে। অন্য Value দিন।',
            ]);
        }
    }
}
