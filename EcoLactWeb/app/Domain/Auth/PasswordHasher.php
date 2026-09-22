<?php

namespace App\Domain\Auth;

interface PasswordHasher
{
    public function hash(string $password): string;
}
