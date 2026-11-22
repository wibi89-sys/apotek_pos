<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActiveTerminal extends Model
{
    protected $table = 'active_terminals';
    protected $primaryKey = 'terminal_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'terminal_id',
        'hostname',
        'ip_address',
        'user_id',
        'last_ping_at',
    ];

    protected $casts = [
        'last_ping_at' => 'datetime',
    ];

    public static function ping(string $terminalId, ?string $userId = null): void
    {
        static::updateOrCreate(
            ['terminal_id' => $terminalId],
            [
                'hostname' => gethostname(),
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_id' => $userId,
                'last_ping_at' => now(),
            ]
        );
    }
}
