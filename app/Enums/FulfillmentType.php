<?php

namespace App\Enums;

enum FulfillmentType: string
{
    case Vendor = 'vendor';
    case Tisilo = 'tisilo';

    public function label(): string
    {
        return match ($this) {
            self::Vendor => 'Fulfilled by Vendor',
            self::Tisilo => 'Fulfilled by Tisilo',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
