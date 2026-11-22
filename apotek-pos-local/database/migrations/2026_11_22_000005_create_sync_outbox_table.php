<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_outbox', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('entity_type'); // transaction, shift, batch
            $table->string('entity_id');
            $table->string('action')->default('create');
            $table->json('payload');
            $table->string('status')->default('pending'); // pending, synced, failed
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_outbox');
    }
};
