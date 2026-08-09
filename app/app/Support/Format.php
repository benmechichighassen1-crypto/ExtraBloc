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
    /**
     * Rôle CONTEXTUEL de l'intervenant pour un acte donné (déduit du champ
     * ERP où son CodInterv apparaît : NomTech, Penseus, Aide2, Aide3,
     * NomInf), à privilégier sur le type STATIQUE de sa fiche intervenant
     * (DesTypInterv) : un même intervenant peut avoir plusieurs fonctions,
     * et son rôle réel pour CET acte est celui-ci, pas sa fiche générale.
     */
    private const ROLE_LABELS = [
        'TECHNICIEN' => 'Technicien(ne)',
        'PENSEUSE' => 'Panseur/Panseuse',
        'AIDE_2' => 'Aide 2',
        'AIDE_3' => 'Aide 3',
        'INSTRUMENTISTE' => 'Instrumentiste',
    ];

    public static function role(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return self::ROLE_LABELS[$code] ?? $code;
    }

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
