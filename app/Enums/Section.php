<?php

namespace App\Enums;

/** Blocks of the "formato de encuadre", in the order the client goes through them. */
enum Section: string
{
    case Necesidad = 'necesidad';
    case Condiciones = 'condiciones';
    case Cotizacion = 'cotizacion';
    case Cierre = 'cierre';
}
