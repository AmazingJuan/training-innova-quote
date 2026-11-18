<?php

namespace App\Enums;

/** How the client answers a question of the request form. */
enum InteractionType: string
{
    /** A single field (text, number, date or documents). */
    case Input = 'input';
    /** Choosing among options, which may open a branch of questions. */
    case Options = 'options';
    /** A "from – to" pair of numbers or dates. */
    case Range = 'range';
}
