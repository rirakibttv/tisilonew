<?php

namespace App\Enums;

enum ProductCondition: string
{
    case New = 'new';
    case Refurbished = 'refurbished';
    case Used = 'used';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Refurbished => 'Refurbished',
            self::Used => 'Used',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $condition): array => [$condition->value => $condition->label()])
            ->all();
    }
}
