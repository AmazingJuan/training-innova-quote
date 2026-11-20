<?php

namespace App\Enums;

/** Progress of an Excel export the admin asked for (polled by the page). */
enum ExportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    /** The token is unknown or expired. */
    case Unknown = 'unknown';
}
