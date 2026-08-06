<?php

namespace App\Support;

use Carbon\Carbon;
use Throwable;

/**
 * Normalise l'affichage des dates/heures renvoyées par le driver SQL Server
 * (souvent au format "2026-08-05 08:25:00.0000000" ou "08:00:00.0000000"),
 * pour ne conserver que heure:minute:seconde (et jour/mois/année si demandé).
 */
class Format
{
    public static function time(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    public static function dateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('d/m/Y H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    public static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}
