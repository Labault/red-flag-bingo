<?php

namespace App\Enum;

enum Rarity: string
{
    case Common    = 'common';
    case Legendary = 'legendary';
    case Rare      = 'rare';

    public function emoji(): string
    {
        return match ($this) {
            self::Common    => '⚪',
            self::Legendary => '🟡',
            self::Rare      => '🔵',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Common    => 'Commun',
            self::Legendary => 'Légendaire',
            self::Rare      => 'Rare',
        };
    }
}
