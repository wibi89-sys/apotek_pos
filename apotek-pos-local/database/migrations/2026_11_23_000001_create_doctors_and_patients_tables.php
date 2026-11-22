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
        // 1. Doctors (Data Dokter Penulis Resep)
        Schema::create('doctors', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('sip_number')->nullable(); // Surat Izin Praktik
            $table->string('specialization')->nullable(); // Umum, Sp.A, Sp.PD, dll.
            $table->string('clinic_name')->nullable(); // Nama Klinik / RS / Puskesmas
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Patients (Data Pasien Resep & Pelanggan Apotek)
        Schema::create('patients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('nik', 16)->nullable();
            $table->string('phone')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 1)->nullable(); // L / P
            $table->text('address')->nullable();
            $table->text('allergies')->nullable(); // Catatan riwayat alergi obat
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
        Schema::dropIfExists('doctors');
    }
};
