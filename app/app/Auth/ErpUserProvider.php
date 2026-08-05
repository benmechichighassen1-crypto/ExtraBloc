<?php

namespace App\Auth;

use App\Models\ErpUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\DB;

class ErpUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->findByUsername((string) $identifier);
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
        // Les comptes ERP ne sont jamais modifiés par cette application.
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        $username = $credentials['username'] ?? $credentials['UserName'] ?? null;

        return is_string($username) ? $this->findByUsername($username) : null;
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        if (! $user instanceof ErpUser || ! isset($credentials['password'])) {
            return false;
        }

        $salt = base64_decode((string) $user->getAttribute('PasswordSalt'), true);
        $expected = base64_decode($user->getAuthPassword(), true);
        $iterations = (int) $user->getAttribute('HashIterations');

        if ($salt === false || $expected === false || $iterations < 1) {
            return false;
        }

        $derived = hash_pbkdf2(
            'sha1',
            (string) $credentials['password'],
            $salt,
            $iterations,
            128,
            true,
        );

        return hash_equals($expected, $derived);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void
    {
        // Le hash est géré uniquement par l'ERP.
    }

    private function findByUsername(string $username): ?ErpUser
    {
        $record = DB::table('app.vw_erp_authentification')
            ->where('UserName', $username)
            ->where('Actif', 1)
            ->where(function ($query): void {
                $query->whereNull('CompteExpire')->orWhere('CompteExpire', 0);
            })
            ->first();

        return $record === null ? null : ErpUser::fromErpAttributes((array) $record);
    }
}
