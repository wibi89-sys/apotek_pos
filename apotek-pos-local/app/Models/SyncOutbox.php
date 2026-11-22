<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncOutbox extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'sync_outbox';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'payload',
        'status',
        'attempts',
        'last_error',
        'synced_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'synced_at' => 'datetime',
    ];
}
