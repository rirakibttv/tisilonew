<?php

namespace App\Enums;

enum IncompleteOrderStatus: string
{
    case Incomplete = 'incomplete';
    case Contacted = 'contacted';
    case Recovered = 'recovered';
    case Converted = 'converted';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Incomplete => 'Incomplete',
            self::Contacted => 'Contacted',
            self::Recovered => 'Recovered',
            self::Converted => 'Converted',
            self::Discarded => 'Discarded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Incomplete => 'warning',
            self::Contacted => 'info',
            self::Recovered, self::Converted => 'success',
            self::Discarded => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(
            array_map(fn (self $status): array => [$status->value, $status->label()], self::cases()),
            1,
            0,
        );
    }
}
