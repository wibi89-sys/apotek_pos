<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DrugClass;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'sku',
        'barcode',
        'name',
        'generic_name',
        'category',
        'drug_class',
        'rack_location',
        'base_unit_name',
        'min_stock_base',
        'is_active',
    ];

    protected $casts = [
        'drug_class' => DrugClass::class,
        'min_stock_base' => 'integer',
        'is_active' => 'boolean',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('conversion_factor', 'asc');
    }

    public function defaultUnit(): ?ProductUnit
    {
        return $this->units()->where('is_default_pos', true)->first()
            ?? $this->units()->orderBy('conversion_factor', 'asc')->first();
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function totalStockBase(): int
    {
        return (int) $this->batches()
            ->where('expired_date', '>', now()->toDateString())
            ->sum('stock_base_unit');
    }

    /**
     * High-speed search query optimized for < 50ms POS response time.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        $connection = DB::connection()->getDriverName();

        // Exact match on barcode takes absolute priority
        if ($connection === 'mysql') {
            return $query->where(function (Builder $q) use ($term) {
                $q->where('barcode', $term)
                    ->orWhereRaw('MATCH(name, generic_name, sku) AGAINST(? IN BOOLEAN MODE)', [$term . '*']);
            });
        }

        // Fallback for SQLite in-memory tests
        return $query->where(function (Builder $q) use ($term) {
            $q->where('barcode', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('generic_name', 'like', "%{$term}%");
        });
    }
}
