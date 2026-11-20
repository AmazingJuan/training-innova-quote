<?php

namespace App\Utils;

class Brand
{
    /** Training Corporation's logo, relative to `public/`. */
    public const LOGO = 'logo_training.png';

    public static function logoPath(): string
    {
        return public_path(self::LOGO);
    }
}
