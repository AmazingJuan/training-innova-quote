<?php

namespace App\Utils;

use Illuminate\Support\Str;

class NameNormalizer
{
    /**
     * Turn a display name into a URL-safe slug: ASCII-transliterated,
     * lowercase, with any run of non-alphanumeric characters collapsed into `_`.
     * Example: "Formación Ejecutiva" → "formacion_ejecutiva".
     */
    public static function normalize(string $value): string
    {
        $ascii = strtolower(Str::ascii($value));

        return trim(preg_replace('/[^a-z0-9]+/', '_', $ascii), '_');
    }
}
