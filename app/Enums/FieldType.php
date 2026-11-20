<?php

namespace App\Enums;

/** Kind of value a question asks for. */
enum FieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    /** The client attaches documents (only with InteractionType::Input). */
    case File = 'file';

    /**
     * The types a "from – to" range can have.
     *
     * @return list<self>
     */
    public static function forRange(): array
    {
        return [self::Number, self::Date];
    }
}
