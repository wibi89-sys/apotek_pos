<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'invoice_no',
        'branch_id',
        'terminal_id',
        'shift_id',
        'cashier_id',
        'type',
        'patient_name',
        'doctor_name',
        'subtotal',
        'tuslah_embalase_total',
        'discount_total',
        'tax_total',
        'grand_total',
        'payment_method',
        'paid_amount',
        'change_amount',
        'status',
        'authorized_by_user_id',
        'sync_status',
        'synced_at',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'payment_method' => PaymentMethod::class,
        'status' => TransactionStatus::class,
        'subtotal' => 'string',
        'tuslah_embalase_total' => 'string',
        'discount_total' => 'string',
        'tax_total' => 'string',
        'grand_total' => 'string',
        'paid_amount' => 'string',
        'change_amount' => 'string',
        'synced_at' => 'datetime',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public static function generateInvoiceNo(string $branchCode = 'BR01'): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "INV-{$branchCode}-{$dateStr}-";

        $lastTx = static::where('invoice_no', 'like', "{$prefix}%")
            ->orderBy('invoice_no', 'desc')
            ->first();

        $nextSeq = 1;
        if ($lastTx) {
            $lastSeq = (int) substr($lastTx->invoice_no, -4);
            $nextSeq = $lastSeq + 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }
}
