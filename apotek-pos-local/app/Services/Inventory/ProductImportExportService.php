<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\DrugClass;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportExportService
{
    /**
     * Map category string to DrugClass enum.
     */
    public static function mapDrugClass(?string $category): DrugClass
    {
        $cat = strtolower(trim((string) $category));
        if (str_contains($cat, 'narkotik')) return DrugClass::NARKOTIKA;
        if (str_contains($cat, 'psiko')) return DrugClass::PSIKOTROPIKA;
        if (str_contains($cat, 'prekursor') || str_contains($cat, 'oot')) return DrugClass::PREKURSOR;
        if (str_contains($cat, 'keras')) return DrugClass::KERAS;
        if (str_contains($cat, 'terbatas')) return DrugClass::BEBAS_TERBATAS;
        if (str_contains($cat, 'alkes') || str_contains($cat, 'alat')) return DrugClass::ALKES;
        return DrugClass::BEBAS;
    }

    /**
     * Parse date string to Y-m-d.
     */
    public static function parseExpiryDate(?string $dateStr, int $seedOffset = 0): string
    {
        $str = trim((string) $dateStr);
        if (!empty($str) && $str !== '-') {
            // Try d-m-Y
            try {
                $d = Carbon::createFromFormat('d-m-Y', $str);
                if ($d && $d->isFuture()) {
                    return $d->toDateString();
                }
            } catch (\Throwable) {}

            try {
                $d = Carbon::parse($str);
                if ($d && $d->isFuture()) {
                    return $d->toDateString();
                }
            } catch (\Throwable) {}
        }

        // Realistic healthy expiry date for pharmacy operations (12 to 36 months ahead)
        return now()->addMonths(12 + ($seedOffset % 24))->toDateString();
    }

    /**
     * Import products from a CSV file.
     */
    public function importFromCsv(string $filePath, bool $giveDefaultStock = true): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File CSV tidak ditemukan: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Gagal membuka file CSV.");
        }

        // Read header
        $rawHeader = fgetcsv($handle);
        if (!$rawHeader) {
            fclose($handle);
            throw new \RuntimeException("File CSV kosong.");
        }

        // Normalize header keys
        $header = [];
        foreach ($rawHeader as $idx => $h) {
            // Remove UTF-8 BOM if present
            $cleaned = preg_replace('/[\x{FEFF}]/u', '', trim($h));
            $header[$idx] = strtolower($cleaned);
        }

        $imported = 0;
        $updated = 0;
        $rowNum = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (empty($data) || (count($data) === 1 && empty($data[0]))) {
                continue;
            }

            $row = [];
            foreach ($data as $idx => $val) {
                $key = $header[$idx] ?? "col_{$idx}";
                $row[$key] = trim((string) $val);
            }

            $sku = $row['kode'] ?? '';
            $name = $row['nama'] ?? '';

            if (empty($name)) {
                continue;
            }

            if (empty($sku)) {
                $sku = 'SKU-' . strtoupper(substr(md5($name . microtime()), 0, 8));
            }

            $kategori = $row['kategori'] ?? 'Obat Bebas';
            $drugClass = self::mapDrugClass($kategori);

            $satuanBeli = ($row['satuan_beli'] ?? '') ?: 'Dos';
            $hargaBeli = (float) ($row['harga_beli'] ?? 0);
            $satuan1 = ($row['satuan_1'] ?? '') ?: ($satuanBeli ?: 'Tablet');
            $isi1 = (float) ($row['isi_1'] ?? 1);
            if ($isi1 <= 0) $isi1 = 1;

            $hargaJual1 = (float) ($row['harga_jual_1'] ?? 0);
            if ($hargaJual1 <= 0) {
                // If sell price empty, calculate standard 20% margin from buy price
                $unitBuy = $isi1 > 1 ? ($hargaBeli / $isi1) : $hargaBeli;
                $hargaJual1 = $unitBuy > 0 ? round($unitBuy * 1.20) : 5000;
            }

            $stokMin = (int) ($row['stok_min'] ?? 10);
            $komposisi = ($row['komposisi'] ?? '') ?: (($row['indikasi'] ?? '') ?: null);
            $lokasi = ($row['lokasi'] ?? '') ?: null;
            $noBatch = ($row['no_batch'] ?? '') ?: null;

            // Stock
            $rawStock = $row['stok'] ?? '';
            if (is_numeric($rawStock) && (int) $rawStock > 0) {
                $stockBase = (int) $rawStock;
            } else {
                // Default stock so cashier can immediately test selling
                $stockBase = $giveDefaultStock ? 100 : 0;
            }

            $expiredDate = self::parseExpiryDate($row['expired'] ?? '', $rowNum);
            $batchNum = $noBatch ?: ('BCH-' . date('y') . strtoupper(substr(md5($sku), 0, 6)));

            $buyPriceBase = $isi1 > 1 ? round($hargaBeli / $isi1, 2) : $hargaBeli;

            DB::transaction(function () use (
                $sku, $name, $komposisi, $kategori, $drugClass, $lokasi, $satuan1,
                $stokMin, $satuanBeli, $isi1, $hargaBeli, $hargaJual1, $row,
                $stockBase, $batchNum, $expiredDate, $buyPriceBase,
                &$imported, &$updated
            ) {
                $product = Product::where('sku', $sku)->first();

                if ($product) {
                    $product->update([
                        'name' => $name,
                        'generic_name' => $komposisi ?: $product->generic_name,
                        'category' => $kategori ?: $product->category,
                        'drug_class' => $drugClass,
                        'rack_location' => $lokasi ?: $product->rack_location,
                        'base_unit_name' => $satuan1,
                        'min_stock_base' => $stokMin,
                        'is_active' => true,
                    ]);
                    $updated++;
                } else {
                    $product = Product::create([
                        'sku' => $sku,
                        'name' => $name,
                        'generic_name' => $komposisi,
                        'category' => $kategori,
                        'drug_class' => $drugClass,
                        'rack_location' => $lokasi,
                        'base_unit_name' => $satuan1,
                        'min_stock_base' => $stokMin,
                        'is_active' => true,
                    ]);
                    $imported++;
                }

                // 1. Base Unit (satuan_1)
                ProductUnit::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'unit_name' => $satuan1,
                    ],
                    [
                        'conversion_factor' => 1,
                        'sell_price' => $hargaJual1,
                        'is_default_pos' => true,
                    ]
                );

                // 2. Packaging / Purchase Unit (satuan_beli, if different and isi > 1)
                if (!empty($satuanBeli) && strcasecmp($satuanBeli, $satuan1) !== 0 && $isi1 > 1) {
                    $sellPriceBox = $hargaBeli > 0 ? round($hargaBeli * 1.15) : round($hargaJual1 * $isi1);
                    ProductUnit::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'unit_name' => $satuanBeli,
                        ],
                        [
                            'conversion_factor' => (int) round($isi1),
                            'sell_price' => $sellPriceBox,
                            'is_default_pos' => false,
                        ]
                    );
                }

                // 3. Optional satuan_2, satuan_3, satuan_4
                for ($k = 2; $k <= 4; $k++) {
                    $extraUnit = $row["satuan_{$k}"] ?? '';
                    $extraIsi = (float) ($row["isi_{$k}"] ?? 0);
                    $extraPrice = (float) ($row["harga_jual_{$k}"] ?? 0);

                    if (!empty($extraUnit) && $extraIsi > 1 && $extraPrice > 0) {
                        ProductUnit::updateOrCreate(
                            [
                                'product_id' => $product->id,
                                'unit_name' => $extraUnit,
                            ],
                            [
                                'conversion_factor' => (int) round($extraIsi),
                                'sell_price' => $extraPrice,
                                'is_default_pos' => false,
                            ]
                        );
                    }
                }

                // 4. Batch & Stock
                $batch = ProductBatch::where('product_id', $product->id)
                    ->where('batch_number', $batchNum)
                    ->first();

                if (!$batch) {
                    $batch = ProductBatch::create([
                        'branch_id' => 'BR01',
                        'product_id' => $product->id,
                        'batch_number' => $batchNum,
                        'expired_date' => $expiredDate,
                        'stock_base_unit' => $stockBase,
                        'buy_price_base' => $buyPriceBase,
                        'received_at' => now(),
                    ]);

                    if ($stockBase > 0) {
                        StockMovement::create([
                            'branch_id' => 'BR01',
                            'product_id' => $product->id,
                            'batch_id' => $batch->id,
                            'type' => StockMovementType::ADJUSTMENT_OPNAME,
                            'qty_change_base' => $stockBase,
                            'stock_after_base' => $stockBase,
                            'reference_type' => 'excel_import',
                            'reference_id' => 'INIT-' . date('Ymd'),
                            'notes' => 'Import saldo awal obat via Excel/CSV',
                            'sync_status' => 'pending',
                        ]);
                    }
                }
            });
        }

        fclose($handle);

        return [
            'total_rows' => $rowNum - 1,
            'imported' => $imported,
            'updated' => $updated,
        ];
    }

    /**
     * Export all active products to an Excel-compatible CSV Stream.
     */
    public function exportToCsv(): StreamedResponse
    {
        $fileName = 'Data_Obat_Apotek_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            
            // UTF-8 BOM so Excel opens with proper encoding
            fputs($handle, "\xEF\xBB\xBF");

            // Columns matching user format
            fputcsv($handle, [
                'kode',
                'nama',
                'kategori',
                'stok',
                'satuan_beli',
                'harga_beli',
                'stok_min',
                'satuan_1',
                'satuan_2',
                'satuan_3',
                'satuan_4',
                'isi_1',
                'isi_2',
                'isi_3',
                'isi_4',
                'harga_jual_1',
                'harga_jual_2',
                'harga_jual_3',
                'harga_jual_4',
                'laba_otomatis',
                'suplier',
                'pabrik',
                'expired',
                'indikasi',
                'komposisi',
                'lokasi',
                'no_batch',
            ]);

            Product::with(['units', 'batches' => function ($q) {
                $q->orderBy('expired_date', 'asc');
            }])
            ->chunk(100, function ($products) use ($handle) {
                foreach ($products as $p) {
                    $units = $p->units->sortBy('conversion_factor')->values();
                    $unit1 = $units->first();
                    $unit2 = $units->get(1);
                    $unit3 = $units->get(2);
                    $unit4 = $units->get(3);

                    $totalStock = $p->batches->sum('stock_base_unit');
                    $earliestBatch = $p->batches->first();

                    fputcsv($handle, [
                        $p->sku,
                        $p->name,
                        $p->drug_class->label(),
                        $totalStock,
                        $unit2 ? $unit2->unit_name : $p->base_unit_name,
                        $earliestBatch ? (float) ($earliestBatch->buy_price_base * ($unit2 ? $unit2->conversion_factor : 1)) : 0,
                        $p->min_stock_base,
                        $unit1 ? $unit1->unit_name : $p->base_unit_name,
                        $unit2 ? $unit2->unit_name : '',
                        $unit3 ? $unit3->unit_name : '',
                        $unit4 ? $unit4->unit_name : '',
                        $unit1 ? $unit1->conversion_factor : 1,
                        $unit2 ? $unit2->conversion_factor : '',
                        $unit3 ? $unit3->conversion_factor : '',
                        $unit4 ? $unit4->conversion_factor : '',
                        $unit1 ? (float) $unit1->sell_price : 0,
                        $unit2 ? (float) $unit2->sell_price : '',
                        $unit3 ? (float) $unit3->sell_price : '',
                        $unit4 ? (float) $unit4->sell_price : '',
                        '',
                        '',
                        '',
                        $earliestBatch ? $earliestBatch->expired_date->format('d-m-Y') : '',
                        $p->generic_name ?? '',
                        $p->generic_name ?? '',
                        $p->rack_location ?? '',
                        $earliestBatch ? $earliestBatch->batch_number : '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Download Excel/CSV Blank Template for easy filling.
     */
    public function downloadTemplate(): StreamedResponse
    {
        $fileName = 'Template_Import_Obat.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'kode',
                'nama',
                'kategori',
                'stok',
                'satuan_beli',
                'harga_beli',
                'stok_min',
                'satuan_1',
                'satuan_2',
                'isi_1',
                'isi_2',
                'harga_jual_1',
                'harga_jual_2',
                'expired',
                'komposisi',
                'lokasi',
                'no_batch',
            ]);

            // Sample rows
            fputcsv($handle, [
                'OBT-SAMPLE-01',
                'PARACETAMOL 500MG TAB',
                'Obat Bebas',
                '100',
                'Dos',
                '25000',
                '10',
                'Tablet',
                'Strip',
                '1',
                '10',
                '500',
                '4500',
                date('d-m-Y', strtotime('+2 years')),
                'Paracetamol 500mg',
                'Rak A-01',
                'BCH-PCT-001',
            ]);

            fputcsv($handle, [
                'OBT-SAMPLE-02',
                'AMOXICILLIN 500MG KAP',
                'Obat Keras',
                '50',
                'Box',
                '45000',
                '10',
                'Kapsul',
                'Strip',
                '1',
                '10',
                '800',
                '7500',
                date('d-m-Y', strtotime('+2 years')),
                'Amoxicillin Trihydrate 500mg',
                'Rak B-02',
                'BCH-AMX-001',
            ]);

            fclose($handle);
        }, 200, $headers);
    }
}
