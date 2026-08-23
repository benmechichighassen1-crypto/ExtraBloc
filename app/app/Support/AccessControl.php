<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Vérifications d'accès centralisées. Fichier à FUSIONNER avec
 * app/Support/AccessControl.php existant (Extra Bloc) — n'ajoutez ici que
 * la méthode hasAnapathEditAccess() si les autres méthodes existent déjà.
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

    public static function hasRhAccess(?string $username): bool
    {
        if (! $username) {
            return false;
        }

        return DB::table('app.rh_users')->where('erp_username', $username)->where('actif', 1)->exists();
    }

    /**
     * Accès permanent à la modification/annulation d'une demande d'anapath,
     * sans avoir à saisir le code de sécurité (voir config/registrebloc.php).
     */
    public static function hasAnapathEditAccess(?string $username): bool
    {
        if (! $username) {
            return false;
        }

        return DB::table('app.anapath_editeurs')->where('erp_username', $username)->where('actif', 1)->exists();
    }
}
