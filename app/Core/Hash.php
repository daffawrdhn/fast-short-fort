<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class Hash
{
    public static function make(string $value, array $options = []): string
    {
        $hash = password_hash($value, PASSWORD_ARGON2ID, $options);

        if ($hash === false) {
            throw new RuntimeException('Argon2id hashing failed.');
        }

        return $hash;
    }

    public static function check(string $value, string $hash): bool
    {
        return password_verify($value, $hash);
    }

    public static function needsRehash(string $hash, array $options = []): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $options);
    }

    public static function info(string $hash): array
    {
        return password_get_info($hash);
    }

    public static function generateToken(int $length = 64): string
    {
        return bin2hex(random_bytes($length));
    }
}
