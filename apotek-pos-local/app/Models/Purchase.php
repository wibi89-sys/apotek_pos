<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'invoice_no',
        'supplier_id',
        'branch_id',
        'user_id',
        'purchase_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'grand_total',
        'payment_status',
        'notes',
        'sync_status',
        'synced_at',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'string',
        'tax_amount' => 'string',
        'grand_total' => 'string',
        'synced_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
