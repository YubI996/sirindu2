<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan aplikasi key-value.
 *
 * Dibaca di landing publik (setiap pengunjung), jadi hasilnya di-cache dan
 * cache-nya dibuang saat nilainya diubah.
 */
class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = ['key', 'value'];

    /** Toggle publikasi ringkasan Operasi Timbang di landing publik. */
    public const KEY_TIMBANG_PUBLIK = 'timbang_publik_aktif';

    private static function cacheKey(string $key): string
    {
        return 'app_setting:' . $key;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return Cache::rememberForever(
            self::cacheKey($key),
            fn () => static::where('key', $key)->value('value') ?? $default
        );
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::cacheKey($key));
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::get($key, $default ? '1' : '0');

        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }

    public static function setBool(string $key, bool $value): void
    {
        static::set($key, $value ? '1' : '0');
    }

    /**
     * Apakah ringkasan Operasi Timbang boleh tampil di landing publik.
     * Default true — perilaku sebelum fitur toggle ada tidak berubah.
     */
    public static function timbangPublikAktif(): bool
    {
        return static::getBool(self::KEY_TIMBANG_PUBLIK, true);
    }
}
