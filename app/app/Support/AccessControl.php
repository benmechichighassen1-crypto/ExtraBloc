<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Vérifications d'accès aux écrans Direction et Pré-validation (major).
 * Centralisé ici pour être utilisé à la fois par les middlewares
 * (EnsureDirectionAccess / EnsureMajorAccess) et par la navbar, qui doit
 * n'afficher que les liens réellement accessibles à l'utilisateur connecté
 * (éviter un lien "Direction" visible mais qui renvoie "non autorisé").
 */
class AccessControl
{
    public static function hasDirectionAccess(?string $username): bool
    {
        if (! $username) {
            return false;
        }

        return DB::table('app.direction_users')
            ->where('erp_username', $username)
            ->where('actif', 1)
            ->exists();
    }

    public static function hasMajorAccess(?string $username): bool
    {
        if (! $username) {
            return false;
        }

        return DB::table('app.major_users')->where('erp_username', $username)->where('actif', 1)->exists()
            || self::hasDirectionAccess($username);
    }

    /**
     * Accès RH : lecture seule, filtré sur les déclarations validées, avec
     * export Excel. Séparé de Direction (qui a bien plus de droits).
     */
    public static function hasRhAccess(?string $username): bool
    {
        if (! $username) {
            return false;
        }

        return DB::table('app.rh_users')->where('erp_username', $username)->where('actif', 1)->exists();
    }
}
