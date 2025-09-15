<?php

namespace App\Enums;

use MoonShine\Laravel\Models\MoonshineUserRole;

enum Role: int
{
    case admin = MoonshineUserRole::DEFAULT_ROLE_ID;
    case user = 2;
    case partner = 3;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
