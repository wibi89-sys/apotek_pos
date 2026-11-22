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
        // 1. Suppliers
        Schema::create('suppliers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Products
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->index();
            $table->string('name');
            $table->string('generic_name')->nullable();
            $table->string('category')->nullable();
            $table->string('drug_class'); // DrugClass enum
            $table->string('rack_location')->nullable();
            $table->string('base_unit_name'); // e.g. Tablet, Kapsul, Botol
            $table->integer('min_stock_base')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Fulltext Index for rapid search (< 50ms) on MySQL
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText(['name', 'generic_name', 'sku'], 'idx_product_search');
            }
        });

        // 3. Product Multi-Units
        Schema::create('product_units', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('unit_name'); // Box, Strip, Tablet
            $table->integer('conversion_factor')->default(1);
            $table->string('barcode_unit')->nullable()->index();
            $table->decimal('sell_price', 15, 2)->default(0.00);
            $table->boolean('is_default_pos')->default(false);
            $table->timestamps();
        });

        // 4. Product Batches
        Schema::create('product_batches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('branch_id')->default('BR01');
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('batch_number');
            $table->date('expired_date')->index();
            $table->integer('stock_base_unit')->default(0);
            $table->decimal('buy_price_base', 15, 2)->default(0.00);
            $table->foreignUlid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            // Composite Index FEFO for ultra-fast allocation
            $table->index(['product_id', 'expired_date', 'stock_base_unit'], 'idx_fefo');
        });

        // 5. Stock Movements
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('branch_id')->default('BR01');
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUlid('batch_id')->constrained('product_batches')->cascadeOnDelete();
            $table->string('type'); // StockMovementType enum
            $table->integer('qty_change_base'); // + for in, - for out
            $table->integer('stock_after_base');
            $table->string('reference_type')->nullable(); // purchase, transaction, opname
            $table->string('reference_id')->nullable();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('sync_status')->default('pending'); // pending, synced, failed
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
        });

        // 6. Purchases (Penerimaan Faktur Pembelian PBF)
        Schema::create('purchases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('invoice_no')->unique();
            $table->foreignUlid('supplier_id')->constrained('suppliers');
            $table->string('branch_id')->default('BR01');
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('purchase_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->decimal('grand_total', 15, 2);
            $table->string('payment_status')->default('paid'); // unpaid, partially_paid, paid
            $table->text('notes')->nullable();
            $table->string('sync_status')->default('pending');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // 7. Purchase Items
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products');
            $table->string('batch_number');
            $table->date('expired_date');
            $table->string('unit_name');
            $table->integer('conversion_factor')->default(1);
            $table->decimal('qty_input', 10, 2);
            $table->integer('qty_base');
            $table->decimal('buy_price_unit', 15, 2);
            $table->decimal('buy_price_base', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_batches');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
    }
};
