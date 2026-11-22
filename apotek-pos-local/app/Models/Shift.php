<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'branch_id',
        'terminal_id',
        'user_id',
        'opened_at',
        'closed_at',
        'opening_cash',
        'expected_cash',
        'actual_cash',
        'cash_difference',
        'status',
        'sync_status',
        'synced_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_cash' => 'string',
        'expected_cash' => 'string',
        'actual_cash' => 'string',
        'cash_difference' => 'string',
        'status' => ShiftStatus::class,
        'synced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public static function currentOpenShift(string $terminalId, ?string $userId = null): ?self
    {
        $query = static::where('terminal_id', $terminalId)
            ->where('status', ShiftStatus::OPEN);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->latest('opened_at')->first();
    }

    public function calculateTotalCashSales(): string
    {
        $sum = '0.00';
        $cashSales = $this->transactions()
            ->where('payment_method', PaymentMethod::CASH)
            ->where('status', 'completed')
            ->get();

        foreach ($cashSales as $tx) {
            $sum = bcadd($sum, (string) $tx->grand_total, 2);
        }

        return $sum;
    }

    public function calculateExpectedCash(): string
    {
        $totalCashSales = $this->calculateTotalCashSales();
        return bcadd((string) $this->opening_cash, $totalCashSales, 2);
    }

    public function closeShift(string $actualCashInput): void
    {
        $expected = $this->calculateExpectedCash();
        $diff = bcsub($actualCashInput, $expected, 2);

        $this->update([
            'expected_cash' => $expected,
            'actual_cash' => $actualCashInput,
            'cash_difference' => $diff,
            'closed_at' => now(),
            'status' => ShiftStatus::CLOSED,
        ]);

        // Trigger automatic shift closing backup
        try {
            app(\App\Services\Database\DatabaseBackupService::class)->createBackup('shift_close');
        } catch (\Throwable) {
            // Backup failure should not block shift closing
        }
    }
}
