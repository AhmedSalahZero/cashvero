<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Stores a secret encrypted in the database and hands it back as normal text.
 *
 * Why not Laravel's built-in "encrypted" cast: that one throws an error when it meets a value that was
 * saved BEFORE encryption existed. This one still reads such an old plain value (so nothing breaks
 * between deploying the code and running the conversion migration) and always WRITES encrypted.
 * (Security SEC-02.)
 */
class EncryptedOrPlain implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value; // an old, not yet converted value
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return Crypt::encryptString((string) $value);
    }
}
