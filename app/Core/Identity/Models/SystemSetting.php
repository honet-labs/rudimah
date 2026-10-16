<?php

namespace App\Core\Identity\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $connection = 'platform';
    protected $table = 'system_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'group',
        'is_secret',
    ];

    protected $casts = [
        'is_secret' => 'boolean',
    ];

    /**
     * Ambil nilai pengaturan berdasarkan kunci.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            $setting = static::find($key);
            return $setting !== null ? $setting->value : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * Simpan atau perbarui nilai pengaturan sistem.
     */
    public static function set(string $key, ?string $value, string $group = 'general', bool $isSecret = false): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
                'is_secret' => $isSecret,
            ]
        );
    }
}
