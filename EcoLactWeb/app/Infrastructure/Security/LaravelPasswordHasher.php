<?php

namespace App\Infrastructure\Security;

use App\Domain\Auth\PasswordHasher;
use Illuminate\Support\Facades\Hash;

class LaravelPasswordHasher implements PasswordHasher
{
    public function hash(string $password): string
    {
        return Hash::make($password);
    }
}
