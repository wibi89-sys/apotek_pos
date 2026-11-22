<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Shifts (Manajemen Shift Kasir & Blind Closing)
        Schema::create('shifts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('branch_id')->default('BR01');
            $table->string('terminal_id');
            $table->foreignUlid('user_id')->constrained('users');
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 15, 2)->default(0.00); // Modal Awal
            $table->decimal('expected_cash', 15, 2)->nullable(); // Dihitung sistem
            $table->decimal('actual_cash', 15, 2)->nullable(); // Input fisik blind closing kasir
            $table->decimal('cash_difference', 15, 2)->nullable(); // actual - expected
            $table->string('status')->default('open'); // ShiftStatus: open, closed
            $table->string('sync_status')->default('pending'); // pending, synced, failed
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // 2. Transactions (Nota Kasir POS)
        Schema::create('transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('invoice_no')->unique(); // format: INV-BR01-YYYYMMDD-0001
            $table->string('branch_id')->default('BR01');
            $table->string('terminal_id');
            $table->foreignUlid('shift_id')->constrained('shifts');
            $table->foreignUlid('cashier_id')->constrained('users');
            $table->string('type')->default('otc'); // TransactionType: otc, resep
            $table->string('patient_name')->nullable();
            $table->string('doctor_name')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tuslah_embalase_total', 15, 2)->default(0.00);
            $table->decimal('discount_total', 15, 2)->default(0.00);
            $table->decimal('tax_total', 15, 2)->default(0.00);
            $table->decimal('grand_total', 15, 2);
            $table->string('payment_method')->default('cash'); // PaymentMethod: cash, qris, transfer, debit
            $table->decimal('paid_amount', 15, 2);
            $table->decimal('change_amount', 15, 2)->default(0.00);
            $table->string('status')->default('completed'); // TransactionStatus: completed, voided, returned
            $table->foreignUlid('authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sync_status')->default('pending');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'status']);
        });

        // 3. Transaction Items (Rincian Item Obat FEFO & Racikan)
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products');
            $table->foreignUlid('batch_id')->constrained('product_batches');
            $table->foreignUlid('product_unit_id')->nullable()->constrained('product_units')->nullOnDelete();
            $table->boolean('is_racikan')->default(false);
            $table->string('racikan_group_name')->nullable();
            $table->decimal('qty_input', 10, 2);
            $table->integer('conversion_factor')->default(1);
            $table->integer('qty_base_deducted'); // total unit dasar terpotong dari batch
            $table->decimal('unit_price', 15, 2);
            $table->decimal('hpp_base_snapshot', 15, 2); // HPP batch saat penjualan
            $table->decimal('discount_item', 15, 2)->default(0.00);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('shifts');
    }
};
