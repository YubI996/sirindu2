<?php

namespace App\Services;

use Hashids\Hashids;

class HashIdService
{
    protected static $instances = [];

    public static function encode($id, $connection = 'main'): string
    {
        return self::getInstance($connection)->encode($id);
    }

    public static function decode($hash, $connection = 'main')
    {
        $decoded = self::getInstance($connection)->decode($hash);
        return count($decoded) > 0 ? $decoded[0] : null;
    }

    public static function encodeString($id, $connection = 'main'): string
    {
        return self::getInstance($connection)->encodeHex($id);
    }

    public static function decodeString($hash, $connection = 'main'): ?string
    {
        $decoded = self::getInstance($connection)->decodeHex($hash);
        return !empty($decoded) ? $decoded : null;
    }

    protected static function getInstance($connection = 'main'): Hashids
    {
        if (!isset(self::$instances[$connection])) {
            $salt = config('app.key') . $connection;
            $minLength = 8;
            $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';

            self::$instances[$connection] = new Hashids($salt, $minLength, $alphabet);
        }

        return self::$instances[$connection];
    }
}
