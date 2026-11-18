<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Business days in Colombia: Monday to Friday, except public holidays
 * (Ley 51 de 1983, "Ley Emiliani", and the Easter-based ones).
 *
 * Management's reminders also count in "turns": the two moments of a business
 * day when they go out, 8:00 a. m. and 2:00 p. m. Two and a half business days
 * are five turns (Acta Meeting Control 2, Ajustes Reto 2, 03-11-2026).
 */
final class BusinessCalendar
{
    /** Hours of the reminder turns, in the app's timezone. */
    public const TURN_HOURS = [8, 14];

    /** @var array<int, array<string, true>> */
    private static array $holidays = [];

    public static function isBusinessDay(CarbonInterface $date): bool
    {
        return ! $date->isWeekend() && ! isset(self::holidays($date->year)[$date->format('Y-m-d')]);
    }

    /** The date `$days` business days after `$from` (the day itself does not count). */
    public static function addBusinessDays(CarbonInterface $from, int $days): CarbonImmutable
    {
        $date = CarbonImmutable::instance($from)->startOfDay();

        while ($days > 0) {
            $date = $date->addDay();
            if (self::isBusinessDay($date)) {
                $days--;
            }
        }

        return $date;
    }

    /**
     * How many reminder turns go strictly after `$from` up to `$until`
     * included. A request sent on a Monday at 9:00 reaches its fifth turn on
     * Wednesday at 2:00 p. m. and its tenth on the next Monday at 8:00 a. m.
     */
    public static function turnsBetween(CarbonInterface $from, CarbonInterface $until): int
    {
        $tz = config('app.timezone');
        $from = CarbonImmutable::instance($from)->setTimezone($tz);
        $until = CarbonImmutable::instance($until)->setTimezone($tz);
        $turns = 0;

        for ($day = $from->startOfDay(); $day <= $until; $day = $day->addDay()) {
            if (! self::isBusinessDay($day)) {
                continue;
            }
            foreach (self::TURN_HOURS as $hour) {
                $turn = $day->setTime($hour, 0);
                if ($turn > $from && $turn <= $until) {
                    $turns++;
                }
            }
        }

        return $turns;
    }

    /**
     * Holidays of a year, keyed by Y-m-d.
     *
     * @return array<string, true>
     */
    public static function holidays(int $year): array
    {
        if (isset(self::$holidays[$year])) {
            return self::$holidays[$year];
        }

        $date = fn (int $month, int $day) => CarbonImmutable::create($year, $month, $day);
        // Moved to the next Monday when they do not fall on one (Ley Emiliani).
        $monday = fn (CarbonImmutable $d) => $d->isMonday() ? $d : $d->next(CarbonInterface::MONDAY);
        $easter = self::easterSunday($year);

        $days = [
            $date(1, 1),                      // Año Nuevo
            $monday($date(1, 6)),             // Reyes Magos
            $monday($date(3, 19)),            // San José
            $easter->subDays(3),              // Jueves Santo
            $easter->subDays(2),              // Viernes Santo
            $date(5, 1),                      // Día del Trabajo
            $monday($easter->addDays(39)),    // Ascensión del Señor
            $monday($easter->addDays(60)),    // Corpus Christi
            $monday($easter->addDays(68)),    // Sagrado Corazón
            $monday($date(6, 29)),            // San Pedro y San Pablo
            $date(7, 20),                     // Independencia
            $date(8, 7),                      // Batalla de Boyacá
            $monday($date(8, 15)),            // Asunción de la Virgen
            $monday($date(10, 12)),           // Día de la Raza
            $monday($date(11, 1)),            // Todos los Santos
            $monday($date(11, 11)),           // Independencia de Cartagena
            $date(12, 8),                     // Inmaculada Concepción
            $date(12, 25),                    // Navidad
        ];

        $set = [];
        foreach ($days as $day) {
            $set[$day->format('Y-m-d')] = true;
        }

        return self::$holidays[$year] = $set;
    }

    /** Gregorian Easter Sunday (anonymous algorithm; no calendar extension needed). */
    private static function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day);
    }
}
