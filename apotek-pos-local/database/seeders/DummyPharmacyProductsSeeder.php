<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DrugClass;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DummyPharmacyProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Default Suppliers
        $supplierId = (string) Str::ulid();
        DB::table('suppliers')->insertOrIgnore([
            'id' => $supplierId,
            'code' => 'PBF-ENS',
            'name' => 'PT Enseval Putera Megatrading Tbk',
            'phone' => '021-4609042',
            'address' => 'Jl. Pulo Lentut No. 10, Kawasan Industri Pulogadung, Jakarta Timur',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $baseDrugNames = [
            ['Paracetamol', 'Paracetamol 500mg', DrugClass::BEBAS->value, 'Tablet'],
            ['Amoxicillin Trihydrate', 'Amoxicillin 500mg', DrugClass::KERAS->value, 'Kapsul'],
            ['Ibuprofen Forte', 'Ibuprofen 400mg', DrugClass::BEBAS_TERBATAS->value, 'Tablet'],
            ['Cetirizine HCl', 'Cetirizine 10mg', DrugClass::BEBAS_TERBATAS->value, 'Tablet'],
            ['Omeprazole Sodium', 'Omeprazole 20mg', DrugClass::KERAS->value, 'Kapsul'],
            ['Amlodipine Besilate', 'Amlodipine 10mg', DrugClass::KERAS->value, 'Tablet'],
            ['Metformin HCl', 'Metformin 500mg', DrugClass::KERAS->value, 'Tablet'],
            ['Cefixime Trihydrate', 'Cefixime 100mg', DrugClass::KERAS->value, 'Kapsul'],
            ['Salbutamol Sulfate', 'Salbutamol 4mg', DrugClass::KERAS->value, 'Tablet'],
            ['Antasida Doen', 'Aluminium Hydroxide, Magnesium Hydroxide', DrugClass::BEBAS->value, 'Tablet'],
            ['Dexamethasone', 'Dexamethasone 0.5mg', DrugClass::KERAS->value, 'Tablet'],
            ['Asam Mefenamat', 'Mefenamic Acid 500mg', DrugClass::KERAS->value, 'Kaplet'],
            ['Loratadine', 'Loratadine 10mg', DrugClass::BEBAS_TERBATAS->value, 'Tablet'],
            ['Lansoprazole', 'Lansoprazole 30mg', DrugClass::KERAS->value, 'Kapsul'],
            ['Simvastatin', 'Simvastatin 20mg', DrugClass::KERAS->value, 'Tablet'],
            ['Allopurinol', 'Allopurinol 100mg', DrugClass::KERAS->value, 'Tablet'],
            ['Ciprofloxacin', 'Ciprofloxacin 500mg', DrugClass::KERAS->value, 'Tablet'],
            ['Ranitidine HCl', 'Ranitidine 150mg', DrugClass::KERAS->value, 'Tablet'],
            ['Diazepam', 'Diazepam 2mg', DrugClass::PSIKOTROPIKA->value, 'Tablet'],
            ['Codeine Phosphate', 'Codeine 10mg', DrugClass::NARKOTIKA->value, 'Tablet'],
            ['Pseudoephedrine HCl', 'Pseudoephedrine 30mg', DrugClass::PREKURSOR->value, 'Tablet'],
            ['Tramadol HCl', 'Tramadol 50mg', DrugClass::OOT->value, 'Kapsul'],
            ['Kassa Steril Hidrofil', 'Kassa Medis 16x16', DrugClass::ALKES->value, 'Kotak'],
        ];

        $manufacturers = ['Kimia Farma', 'Kalbe Farma', 'Sanbe Farma', 'Dexa Medica', 'Pharos', 'Bernofarm', 'Novell', 'Tempo Scan', 'Interbat', 'Meprofarm'];

        $productRows = [];
        $unitRows = [];
        $batchRows = [];

        $totalTarget = 5000;
        $baseCount = count($baseDrugNames);
        $mfgCount = count($manufacturers);

        for ($i = 1; $i <= $totalTarget; $i++) {
            $base = $baseDrugNames[($i - 1) % $baseCount];
            $mfg = $manufacturers[($i - 1) % $mfgCount];

            $productId = (string) Str::ulid();
            $sku = sprintf('MED-%05d', $i);
            $barcode = sprintf('899%010d', $i);
            $name = sprintf('%s %s No. %d', $base[0], $mfg, $i);
            $genericName = $base[1];
            $drugClass = $base[2];
            $baseUnit = $base[3];

            $productRows[] = [
                'id' => $productId,
                'sku' => $sku,
                'barcode' => $barcode,
                'name' => $name,
                'generic_name' => $genericName,
                'category' => 'Obat Jadi',
                'drug_class' => $drugClass,
                'rack_location' => sprintf('Rak-%s-%02d', chr(65 + ($i % 6)), ($i % 20) + 1),
                'base_unit_name' => $baseUnit,
                'min_stock_base' => 20,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Unit 1: Base Unit (e.g. Tablet / Kapsul)
            $unitRows[] = [
                'id' => (string) Str::ulid(),
                'product_id' => $productId,
                'unit_name' => $baseUnit,
                'conversion_factor' => 1,
                'barcode_unit' => $barcode,
                'sell_price' => '1500.00',
                'is_default_pos' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Unit 2: Strip (10 base units)
            $unitRows[] = [
                'id' => (string) Str::ulid(),
                'product_id' => $productId,
                'unit_name' => 'Strip',
                'conversion_factor' => 10,
                'barcode_unit' => sprintf('898%010d', $i),
                'sell_price' => '14000.00',
                'is_default_pos' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Unit 3: Box (100 base units)
            $unitRows[] = [
                'id' => (string) Str::ulid(),
                'product_id' => $productId,
                'unit_name' => 'Box',
                'conversion_factor' => 100,
                'barcode_unit' => sprintf('897%010d', $i),
                'sell_price' => '135000.00',
                'is_default_pos' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Batch 1 (Near expiry: +6 months)
            $batchRows[] = [
                'id' => (string) Str::ulid(),
                'branch_id' => 'BR01',
                'product_id' => $productId,
                'batch_number' => sprintf('BAT-A-%05d', $i),
                'expired_date' => now()->addMonths(6)->toDateString(),
                'stock_base_unit' => 150,
                'buy_price_base' => '1000.00',
                'supplier_id' => $supplierId,
                'received_at' => now()->subMonths(2),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Batch 2 (Further expiry: +18 months)
            $batchRows[] = [
                'id' => (string) Str::ulid(),
                'branch_id' => 'BR01',
                'product_id' => $productId,
                'batch_number' => sprintf('BAT-B-%05d', $i),
                'expired_date' => now()->addMonths(18)->toDateString(),
                'stock_base_unit' => 300,
                'buy_price_base' => '1050.00',
                'supplier_id' => $supplierId,
                'received_at' => now()->subMonth(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insert in chunks of 500 to keep memory low and speed high
            if (count($productRows) >= 500) {
                DB::table('products')->insert($productRows);
                DB::table('product_units')->insert($unitRows);
                DB::table('product_batches')->insert($batchRows);
                $productRows = [];
                $unitRows = [];
                $batchRows = [];
            }
        }

        if (! empty($productRows)) {
            DB::table('products')->insert($productRows);
            DB::table('product_units')->insert($unitRows);
            DB::table('product_batches')->insert($batchRows);
        }
    }
}
