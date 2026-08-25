<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Opening = 'opening';
    case Adjustment = 'adjustment';
    case Purchase = 'purchase';
    case Reservation = 'reservation';
    case ReservationRelease = 'reservation_release';
    case Sale = 'sale';
    case CustomerReturn = 'customer_return';
    case Damage = 'damage';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
}
