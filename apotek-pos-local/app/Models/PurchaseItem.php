<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'batch_number',
        'expired_date',
        'unit_name',
        'conversion_factor',
        'qty_input',
        'qty_base',
        'buy_price_unit',
        'buy_price_base',
        'subtotal',
    ];

    protected $casts = [
        'expired_date' => 'date',
        'conversion_factor' => 'integer',
        'qty_input' => 'string',
        'qty_base' => 'integer',
        'buy_price_unit' => 'string',
        'buy_price_base' => 'string',
        'subtotal' => 'string',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
