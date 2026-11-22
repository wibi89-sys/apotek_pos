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
        Schema::create('system_meta', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->longText('value')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('active_terminals', function (Blueprint $table) {
            $table->string('terminal_id', 64)->primary();
            $table->string('hostname');
            $table->string('ip_address', 45);
            $table->ulid('user_id')->nullable();
            $table->timestamp('last_ping_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('active_terminals');
        Schema::dropIfExists('system_meta');
    }
};
