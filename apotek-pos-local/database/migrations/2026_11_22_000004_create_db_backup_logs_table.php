<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('db_backup_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('filename');
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('storage_path');
            $table->string('trigger_type')->default('manual'); // scheduled, shift_close, manual, pre_restore
            $table->string('status')->default('completed'); // completed, failed
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'trigger_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_backup_logs');
    }
};
