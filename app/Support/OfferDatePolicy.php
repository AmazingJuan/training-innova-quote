<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Rules for the date a client expects the commercial offer by (Acta Meeting
 * Control 2, Ajustes Reto 2): it cannot be sooner than 3 business days after
 * the request is sent, so management gets at least one reminder before it.
 */
final class OfferDatePolicy
{
    public const MIN_BUSINESS_DAYS = 3;

    /** The soonest offer date for a request sent at `$sentAt`. */
    public function minimumFor(CarbonInterface $sentAt): CarbonImmutable
    {
        return BusinessCalendar::addBusinessDays($sentAt, self::MIN_BUSINESS_DAYS);
    }

    /**
     * The date as the form sends it (d/m/Y, written by the browser in es-CO)
     * or as an ISO date. Null when it is not a real date (31/02/2026 is not
     * rolled over to March).
     */
    public function parse(string $answer): ?CarbonImmutable
    {
        foreach (['!d/m/Y', '!Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, trim($answer), new DateTimeZone(config('app.timezone')));
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return CarbonImmutable::instance($date);
            }
        }

        return null;
    }
}
