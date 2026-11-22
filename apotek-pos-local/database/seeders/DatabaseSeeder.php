<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Owner (PSA)
        User::firstOrCreate(
            ['username' => 'owner'],
            [
                'branch_id' => 'BR01',
                'name' => 'H. Rahmat (Pemilik Apotek)',
                'email' => 'owner@apotek.local',
                'password' => bcrypt('password123'),
                'role' => \App\Enums\UserRole::OWNER,
                'is_active' => true,
            ]
        );

        // 2. Apoteker Penanggung Jawab (APA / Supervisor)
        User::firstOrCreate(
            ['username' => 'apoteker'],
            [
                'branch_id' => 'BR01',
                'name' => 'Apt. Budi Santoso, S.Farm',
                'email' => 'apoteker@apotek.local',
                'password' => bcrypt('password123'),
                'role' => \App\Enums\UserRole::APOTEKER,
                'fingerprint_template' => 'FP_ISO19794_2_MOCK_SUPERVISOR_SAMPLE',
                'is_active' => true,
            ]
        );

        // 3. Petugas Kasir (TTK)
        User::firstOrCreate(
            ['username' => 'kasir'],
            [
                'branch_id' => 'BR01',
                'name' => 'Siti Aminah (Kasir)',
                'email' => 'kasir@apotek.local',
                'password' => bcrypt('password123'),
                'role' => \App\Enums\UserRole::KASIR,
                'is_active' => true,
            ]
        );
    }
}
