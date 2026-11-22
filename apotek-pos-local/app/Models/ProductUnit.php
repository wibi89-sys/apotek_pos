<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'product_id',
        'unit_name',
        'conversion_factor',
        'barcode_unit',
        'sell_price',
        'is_default_pos',
    ];

    protected $casts = [
        'conversion_factor' => 'integer',
        'sell_price' => 'string', // Keep as string for bcmath precision
        'is_default_pos' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
