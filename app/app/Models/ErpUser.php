<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class ErpUser extends Authenticatable
{
    protected $guarded = [];

    protected $hidden = [
        'PasswordHash',
        'PasswordSalt',
        'HashIterations',
    ];

    public static function fromErpAttributes(array $attributes): self
    {
        $user = new self();
        $user->setRawAttributes($attributes);

        return $user;
    }

    public function getAuthIdentifierName(): string
    {
        return 'UserName';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getAttribute('UserName');
    }

    public function getAuthPassword(): string
    {
        return (string) $this->getAttribute('PasswordHash');
    }
}
