<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DrugClass;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Services\Inventory\StockFefoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockFefoTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;
    protected StockFefoService $fefoService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fefoService = app(StockFefoService::class);

        $this->product = Product::create([
            'sku' => 'MED-TEST-FEFO-01',
            'barcode' => '8991234567890',
            'name' => 'Paracetamol 500mg Uji',
            'generic_name' => 'Paracetamol',
            'drug_class' => DrugClass::BEBAS,
            'base_unit_name' => 'Tablet',
            'min_stock_base' => 10,
            'is_active' => true,
        ]);

        ProductUnit::create([
            'product_id' => $this->product->id,
            'unit_name' => 'Tablet',
            'conversion_factor' => 1,
            'sell_price' => '1000.00',
            'is_default_pos' => true,
        ]);
        ProductUnit::create([
            'product_id' => $this->product->id,
            'unit_name' => 'Strip',
            'conversion_factor' => 10,
            'sell_price' => '9500.00',
            'is_default_pos' => false,
        ]);
    }

    public function test_single_batch_deduction_is_accurate_and_records_movement(): void
    {
        $batch = ProductBatch::create([
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-001',
            'expired_date' => now()->addMonths(6)->toDateString(),
            'stock_base_unit' => 100,
            'buy_price_base' => '700.00',
        ]);

        $allocations = $this->fefoService->deductStock(
            product: $this->product,
            neededBaseQty: 30,
            referenceType: 'transaction',
            referenceId: 'INV-TEST-001'
        );

        $this->assertCount(1, $allocations);
        $this->assertEquals(30, $allocations[0]['qty_deducted_base']);
        $this->assertEquals('700.00', $allocations[0]['buy_price_base']);

        $this->assertEquals(70, $batch->fresh()->stock_base_unit);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'batch_id' => $batch->id,
            'type' => StockMovementType::OUT_SALE->value,
            'qty_change_base' => -30,
            'stock_after_base' => 70,
            'reference_type' => 'transaction',
            'reference_id' => 'INV-TEST-001',
        ]);
    }

    public function test_automatic_splitting_across_multiple_batches_ordered_by_fefo(): void
    {
        // Batch 1: Expiring in 3 months, only 20 tablets
        $batchNear = ProductBatch::create([
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-NEAR',
            'expired_date' => now()->addMonths(3)->toDateString(),
            'stock_base_unit' => 20,
            'buy_price_base' => '650.00',
        ]);

        // Batch 2: Expiring in 12 months, 100 tablets
        $batchFar = ProductBatch::create([
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-FAR',
            'expired_date' => now()->addMonths(12)->toDateString(),
            'stock_base_unit' => 100,
            'buy_price_base' => '700.00',
        ]);

        // Deduct 50 tablets: should take 20 from BATCH-NEAR and 30 from BATCH-FAR
        $allocations = $this->fefoService->deductStock(
            product: $this->product,
            neededBaseQty: 50,
            referenceType: 'transaction',
            referenceId: 'INV-SPLIT-001'
        );

        $this->assertCount(2, $allocations);

        // First allocation
        $this->assertEquals($batchNear->id, $allocations[0]['batch_id']);
        $this->assertEquals(20, $allocations[0]['qty_deducted_base']);
        $this->assertEquals('650.00', $allocations[0]['buy_price_base']);

        // Second allocation
        $this->assertEquals($batchFar->id, $allocations[1]['batch_id']);
        $this->assertEquals(30, $allocations[1]['qty_deducted_base']);
        $this->assertEquals('700.00', $allocations[1]['buy_price_base']);

        // Check remaining stocks
        $this->assertEquals(0, $batchNear->fresh()->stock_base_unit);
        $this->assertEquals(70, $batchFar->fresh()->stock_base_unit);

        // Check movements
        $this->assertEquals(2, StockMovement::where('product_id', $this->product->id)->count());
    }

    public function test_skips_expired_batches_even_with_available_stock(): void
    {
        // Expired batch (expired yesterday) with 50 tablets
        $expiredBatch = ProductBatch::create([
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-EXPIRED',
            'expired_date' => now()->subDay()->toDateString(),
            'stock_base_unit' => 50,
            'buy_price_base' => '600.00',
        ]);

        // Fresh batch with 100 tablets
        $validBatch = ProductBatch::create([
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-VALID',
            'expired_date' => now()->addMonths(6)->toDateString(),
            'stock_base_unit' => 100,
            'buy_price_base' => '700.00',
        ]);

        $allocations = $this->fefoService->deductStock(
            product: $this->product,
            neededBaseQty: 25,
            referenceType: 'transaction',
            referenceId: 'INV-SKIP-001'
        );

        $this->assertCount(1, $allocations);
        $this->assertEquals($validBatch->id, $allocations[0]['batch_id']);
        $this->assertEquals(25, $allocations[0]['qty_deducted_base']);

        // Expired batch stock MUST NOT be touched
        $this->assertEquals(50, $expiredBatch->fresh()->stock_base_unit);
        $this->assertEquals(75, $validBatch->fresh()->stock_base_unit);
    }

    public function test_rolls_back_when_total_stock_is_insufficient(): void
    {
        $batch = ProductBatch::create([
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-LIMITED',
            'expired_date' => now()->addMonths(6)->toDateString(),
            'stock_base_unit' => 15,
            'buy_price_base' => '700.00',
        ]);

        $this->expectException(InsufficientStockException::class);

        try {
            // Attempt to deduct 20 when only 15 is available
            $this->fefoService->deductStock(
                product: $this->product,
                neededBaseQty: 20,
                referenceType: 'transaction',
                referenceId: 'INV-FAIL-001'
            );
        } finally {
            // Stock and movements must be untouched due to rollback
            $this->assertEquals(15, $batch->fresh()->stock_base_unit);
            $this->assertEquals(0, StockMovement::count());
        }
    }

    public function test_total_mutation_sum_equals_batch_stock_change(): void
    {
        $batch = ProductBatch::create([
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-AUDIT',
            'expired_date' => now()->addMonths(12)->toDateString(),
            'stock_base_unit' => 100,
            'buy_price_base' => '700.00',
        ]);

        $this->fefoService->deductStock($this->product, 15, 'transaction', 'INV-01');
        $this->fefoService->deductStock($this->product, 25, 'transaction', 'INV-02');
        $this->fefoService->deductStock($this->product, 10, 'transaction', 'INV-03');

        $totalDeducted = StockMovement::where('batch_id', $batch->id)->sum('qty_change_base');
        $this->assertEquals(-50, $totalDeducted);
        $this->assertEquals(50, $batch->fresh()->stock_base_unit);
    }
}
