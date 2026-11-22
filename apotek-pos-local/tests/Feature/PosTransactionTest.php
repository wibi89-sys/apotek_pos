<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DrugClass;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\Shift;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Pos\CompletePosTransactionAction;
use App\Services\Pos\VoidPosTransactionAction;
use App\Services\Printing\ReceiptPayloadBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PosTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create([
            'name' => 'Kasir Utama',
            'role' => UserRole::KASIR,
        ]);

        $this->shift = Shift::create([
            'branch_id' => 'BR01',
            'terminal_id' => 'POS01',
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_cash' => '100000.00',
            'status' => ShiftStatus::OPEN,
        ]);
    }

    public function test_shift_open_and_close_with_blind_variance(): void
    {
        $this->assertEquals(ShiftStatus::OPEN, $this->shift->status);
        $this->assertEquals('100000.00', $this->shift->opening_cash);

        // Simulate creating a cash transaction of 50,000
        Transaction::create([
            'invoice_no' => 'INV-BR01-20261122-0001',
            'branch_id' => 'BR01',
            'terminal_id' => 'POS01',
            'shift_id' => $this->shift->id,
            'cashier_id' => $this->cashier->id,
            'type' => TransactionType::OTC,
            'subtotal' => '50000.00',
            'grand_total' => '50000.00',
            'payment_method' => PaymentMethod::CASH,
            'paid_amount' => '50000.00',
            'change_amount' => '0.00',
            'status' => TransactionStatus::COMPLETED,
        ]);

        // Blind Closing: Kasir inputs actual physical cash: 160000 (Expected: 100000 + 50000 = 150000 => Selisih LEBIH 10000)
        $this->shift->closeShift('160000.00');
        $this->shift->refresh();

        $this->assertEquals(ShiftStatus::CLOSED, $this->shift->status);
        $this->assertEquals('150000.00', number_format((float) $this->shift->expected_cash, 2, '.', ''));
        $this->assertEquals('160000.00', number_format((float) $this->shift->actual_cash, 2, '.', ''));
        $this->assertEquals('10000.00', number_format((float) $this->shift->cash_difference, 2, '.', ''));
        $this->assertNotNull($this->shift->closed_at);
    }

    public function test_complete_otc_transaction_deducts_stock_via_fefo(): void
    {
        $product = Product::create([
            'sku' => 'MED-TEST-01',
            'barcode' => '8991234567890',
            'name' => 'Paracetamol 500mg',
            'drug_class' => DrugClass::BEBAS,
            'base_unit_name' => 'Tablet',
            'is_active' => true,
        ]);

        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Strip',
            'conversion_factor' => 10,
            'sell_price' => '15000.00',
            'is_base' => false,
        ]);

        // Batch 1 (Near expiry: 30 days) - 15 tablets
        $batch1 = ProductBatch::create([
            'product_id' => $product->id,
            'batch_number' => 'BATCH-NEAR',
            'expired_date' => now()->addDays(30),
            'stock_base_unit' => 15,
            'buy_price_base' => '800.00',
        ]);

        // Batch 2 (Far expiry: 300 days) - 50 tablets
        $batch2 = ProductBatch::create([
            'product_id' => $product->id,
            'batch_number' => 'BATCH-FAR',
            'expired_date' => now()->addDays(300),
            'stock_base_unit' => 50,
            'buy_price_base' => '850.00',
        ]);

        // Sell 2 strips = 20 tablets.
        // FEFO should allocate: 15 tablets from Batch 1, and 5 tablets from Batch 2.
        $action = app(CompletePosTransactionAction::class);
        $transaction = $action->execute(
            shift: $this->shift,
            cashier: $this->cashier,
            type: TransactionType::OTC,
            cartItems: [
                [
                    'product_id' => $product->id,
                    'product_unit_id' => $unit->id,
                    'qty_input' => 2,
                    'conversion_factor' => 10,
                    'unit_price' => '15000.00',
                    'discount_item' => '0.00',
                ],
            ],
            paymentMethod: PaymentMethod::CASH,
            paidAmount: '50000.00'
        );

        $this->assertEquals(TransactionStatus::COMPLETED, $transaction->status);
        $this->assertEquals('30000.00', $transaction->grand_total);
        $this->assertEquals('20000.00', $transaction->change_amount);

        // Verify batch balances
        $batch1->refresh();
        $batch2->refresh();
        $this->assertEquals(0, $batch1->stock_base_unit);
        $this->assertEquals(45, $batch2->stock_base_unit);

        // Verify Transaction Items snapshot HPP
        $this->assertCount(2, $transaction->items);
        $item1 = $transaction->items->where('batch_id', $batch1->id)->first();
        $item2 = $transaction->items->where('batch_id', $batch2->id)->first();

        $this->assertEquals(15, $item1->qty_base_deducted);
        $this->assertEquals('800.00', number_format((float) $item1->hpp_base_snapshot, 2, '.', ''));
        $this->assertEquals(5, $item2->qty_base_deducted);
        $this->assertEquals('850.00', number_format((float) $item2->hpp_base_snapshot, 2, '.', ''));
    }

    public function test_complete_prescription_sale_with_tuslah_embalase(): void
    {
        $product = Product::create([
            'sku' => 'MED-TEST-02',
            'barcode' => '8991234567891',
            'name' => 'Amoxicillin 500mg',
            'drug_class' => DrugClass::KERAS,
            'base_unit_name' => 'Kapsul',
            'is_active' => true,
        ]);

        ProductBatch::create([
            'product_id' => $product->id,
            'batch_number' => 'AMX-001',
            'expired_date' => now()->addDays(200),
            'stock_base_unit' => 100,
            'buy_price_base' => '1000.00',
        ]);

        $action = app(CompletePosTransactionAction::class);
        $transaction = $action->execute(
            shift: $this->shift,
            cashier: $this->cashier,
            type: TransactionType::RESEP,
            cartItems: [
                [
                    'product_id' => $product->id,
                    'qty_input' => 10,
                    'conversion_factor' => 1,
                    'unit_price' => '2500.00',
                    'discount_item' => '0.00',
                ],
            ],
            paymentMethod: PaymentMethod::QRIS,
            paidAmount: '30000.00',
            tuslahEmbalaseTotal: '5000.00',
            patientName: 'Ny. Siti Aminah',
            doctorName: 'dr. Hendra Sp.PD'
        );

        // Subtotal: 10 * 2500 = 25,000. Tuslah: 5,000. Grand Total: 30,000
        $this->assertEquals('25000.00', $transaction->subtotal);
        $this->assertEquals('5000.00', $transaction->tuslah_embalase_total);
        $this->assertEquals('30000.00', $transaction->grand_total);
        $this->assertEquals('Ny. Siti Aminah', $transaction->patient_name);
        $this->assertEquals('dr. Hendra Sp.PD', $transaction->doctor_name);
    }

    public function test_void_transaction_with_supervisor_pin_restores_stock(): void
    {
        $product = Product::create([
            'sku' => 'MED-TEST-03',
            'barcode' => '8991234567892',
            'name' => 'Antasida DOEN',
            'drug_class' => DrugClass::BEBAS,
            'base_unit_name' => 'Tablet',
            'is_active' => true,
        ]);

        $batch = ProductBatch::create([
            'product_id' => $product->id,
            'batch_number' => 'ATD-001',
            'expired_date' => now()->addDays(150),
            'stock_base_unit' => 50,
            'buy_price_base' => '500.00',
        ]);

        $posAction = app(CompletePosTransactionAction::class);
        $transaction = $posAction->execute(
            shift: $this->shift,
            cashier: $this->cashier,
            type: TransactionType::OTC,
            cartItems: [
                [
                    'product_id' => $product->id,
                    'qty_input' => 10,
                    'conversion_factor' => 1,
                    'unit_price' => '1000.00',
                ],
            ],
            paymentMethod: PaymentMethod::CASH,
            paidAmount: '10000.00'
        );

        $batch->refresh();
        $this->assertEquals(40, $batch->stock_base_unit);

        // Wrong supervisor PIN should be rejected
        $voidAction = app(VoidPosTransactionAction::class);
        $this->expectException(InvalidArgumentException::class);
        $voidAction->execute($transaction, '999999', 'Salah input kasir');
    }

    public function test_void_transaction_success_restores_batch_stock(): void
    {
        $product = Product::create([
            'sku' => 'MED-TEST-04',
            'barcode' => '8991234567893',
            'name' => 'Oralit',
            'drug_class' => DrugClass::BEBAS,
            'base_unit_name' => 'Sachet',
            'is_active' => true,
        ]);

        $batch = ProductBatch::create([
            'product_id' => $product->id,
            'batch_number' => 'ORL-001',
            'expired_date' => now()->addDays(180),
            'stock_base_unit' => 30,
            'buy_price_base' => '1000.00',
        ]);

        $posAction = app(CompletePosTransactionAction::class);
        $transaction = $posAction->execute(
            shift: $this->shift,
            cashier: $this->cashier,
            type: TransactionType::OTC,
            cartItems: [
                [
                    'product_id' => $product->id,
                    'qty_input' => 5,
                    'conversion_factor' => 1,
                    'unit_price' => '1500.00',
                ],
            ],
            paymentMethod: PaymentMethod::CASH,
            paidAmount: '10000.00'
        );

        $batch->refresh();
        $this->assertEquals(25, $batch->stock_base_unit);

        // Correct PIN ('123456')
        $voidAction = app(VoidPosTransactionAction::class);
        $voidedTx = $voidAction->execute($transaction, '123456', 'Customer batalkan pesanan');

        $this->assertEquals(TransactionStatus::VOIDED, $voidedTx->status);
        $batch->refresh();
        $this->assertEquals(30, $batch->stock_base_unit); // Stock restored!
    }

    public function test_receipt_payload_builder_structure(): void
    {
        $product = Product::create([
            'sku' => 'MED-TEST-05',
            'barcode' => '8991234567894',
            'name' => 'OBH Tropica',
            'drug_class' => DrugClass::BEBAS_TERBATAS,
            'base_unit_name' => 'Botol',
            'is_active' => true,
        ]);

        $batch = ProductBatch::create([
            'product_id' => $product->id,
            'batch_number' => 'OBH-99',
            'expired_date' => now()->addDays(250),
            'stock_base_unit' => 20,
            'buy_price_base' => '12000.00',
        ]);

        $posAction = app(CompletePosTransactionAction::class);
        $transaction = $posAction->execute(
            shift: $this->shift,
            cashier: $this->cashier,
            type: TransactionType::OTC,
            cartItems: [
                [
                    'product_id' => $product->id,
                    'qty_input' => 1,
                    'conversion_factor' => 1,
                    'unit_price' => '18000.00',
                ],
            ],
            paymentMethod: PaymentMethod::CASH,
            paidAmount: '20000.00'
        );

        $builder = new ReceiptPayloadBuilderService();
        $payload58 = $builder->build($transaction, 58);
        $payload80 = $builder->build($transaction, 80);

        $this->assertEquals(58, $payload58['paper_width']);
        $this->assertEquals(80, $payload80['paper_width']);
        $this->assertEquals($transaction->invoice_no, $payload58['transaction']['invoice_no']);
        $this->assertNotEmpty($payload58['items']);
        $this->assertEquals('18.000', $payload58['totals']['grand_total']);
        $this->assertEquals('2.000', $payload58['totals']['change_amount']);
        $this->assertTrue($payload58['open_drawer']);
    }
}
