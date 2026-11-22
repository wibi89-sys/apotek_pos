<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'batch_id',
        'product_unit_id',
        'is_racikan',
        'racikan_group_name',
        'qty_input',
        'conversion_factor',
        'qty_base_deducted',
        'unit_price',
        'hpp_base_snapshot',
        'discount_item',
        'subtotal',
    ];

    protected $casts = [
        'is_racikan' => 'boolean',
        'qty_input' => 'string',
        'conversion_factor' => 'integer',
        'qty_base_deducted' => 'integer',
        'unit_price' => 'string',
        'hpp_base_snapshot' => 'string',
        'discount_item' => 'string',
        'subtotal' => 'string',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
