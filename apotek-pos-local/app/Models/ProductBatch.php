<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductBatch extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'branch_id',
        'product_id',
        'batch_number',
        'expired_date',
        'stock_base_unit',
        'buy_price_base',
        'supplier_id',
        'received_at',
    ];

    protected $casts = [
        'expired_date' => 'date',
        'stock_base_unit' => 'integer',
        'buy_price_base' => 'string',
        'received_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'batch_id');
    }

    public function isExpired(): bool
    {
        return $this->expired_date->isPast();
    }
}
