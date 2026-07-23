<?php

namespace App\Enums;

enum CheckStatus: string
{
    case Open = 'open';
    case AwaitingPayment = 'awaiting_payment';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Açık',
            self::AwaitingPayment => 'Hesap Bekliyor',
            self::Closed => 'Kapalı',
            self::Cancelled => 'İptal',
        };
    }
}
