<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory, HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'product_id',
        'batch_id',
        'type',
        'qty_change_base',
        'stock_after_base',
        'reference_type',
        'reference_id',
        'user_id',
        'notes',
        'sync_status',
        'synced_at',
        'created_at',
    ];

    protected $casts = [
        'type' => StockMovementType::class,
        'qty_change_base' => 'integer',
        'stock_after_base' => 'integer',
        'created_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
