<?php

namespace App\Enums;

enum TableStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case AwaitingPayment = 'awaiting_payment';
    case Reserved = 'reserved';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Boş',
            self::Occupied => 'Dolu',
            self::AwaitingPayment => 'Hesap Bekliyor',
            self::Reserved => 'Rezerve',
            self::Inactive => 'Pasif',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Available => 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30',
            self::Occupied => 'bg-rose-500/15 text-rose-300 ring-rose-500/30',
            self::AwaitingPayment => 'bg-amber-500/15 text-amber-300 ring-amber-500/30',
            self::Reserved => 'bg-sky-500/15 text-sky-300 ring-sky-500/30',
            self::Inactive => 'bg-slate-500/15 text-slate-300 ring-slate-500/30',
        };
    }
}
