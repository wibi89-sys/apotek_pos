<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemMeta extends Model
{
    protected $table = 'system_meta';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'key',
        'value',
        'updated_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $meta = static::find($key);
        return $meta ? $meta->value : $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()]
        );
    }
}
