<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\DrugClass;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
     * Download pure Microsoft Excel (.xlsx) template with styling and instructions sheet.
     */
    public function downloadTemplateXlsx(): StreamedResponse
    {
        $fileName = 'Template_Import_Obat.xlsx';

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Apotek POS')
            ->setTitle('Template Import Master Obat')
            ->setSubject('Template Excel untuk Import Master Data Obat');

        // Sheet 1: Template Data Obat
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Obat');

        $headers = [
            'A1' => ['kode', 'Kode / Barcode Obat (SKU)'],
            'B1' => ['nama', 'Nama Obat & Dosis'],
            'C1' => ['kategori', 'Golongan BPOM'],
            'D1' => ['stok', 'Stok Fisik Awal'],
            'E1' => ['satuan_beli', 'Satuan Pembelian (Box/Dos/Botol)'],
            'F1' => ['harga_beli', 'Harga Beli (per Satuan Beli)'],
            'G1' => ['stok_min', 'Stok Minimum'],
            'H1' => ['satuan_1', 'Satuan Kasir / Eceran (Tablet/Kapsul)'],
            'I1' => ['isi_1', 'Isi Satuan 1 per Satuan Beli'],
            'J1' => ['harga_jual_1', 'Harga Jual Eceran 1'],
            'K1' => ['harga_resep_1', 'Harga Resep 1'],
            'L1' => ['satuan_2', 'Satuan Menengah (Strip/Blister)'],
            'M1' => ['isi_2', 'Isi Satuan 1 per Satuan 2'],
            'N1' => ['harga_jual_2', 'Harga Jual Satuan 2'],
            'O1' => ['harga_resep_2', 'Harga Resep Satuan 2'],
            'P1' => ['expired', 'Tgl Expired (DD-MM-YYYY)'],
            'Q1' => ['komposisi', 'Zat Aktif / Komposisi'],
            'R1' => ['lokasi', 'Lokasi Rak'],
            'S1' => ['no_batch', 'Nomor Batch'],
        ];

        // Header Values
        foreach ($headers as $cell => $info) {
            $sheet->setCellValue($cell, $info[0]);
        }

        // Header Styling
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '312E81'], // Indigo 900
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '475569'],
                ],
            ],
        ];
        $sheet->getStyle('A1:S1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Sample Data Rows
        $sampleRows = [
            [
                'OBT-SAMPLE-01',
                'PARACETAMOL 500MG TABLET',
                'Obat Bebas',
                10,
                'Box',
                50000,
                5,
                'Tablet',
                100,
                800,
                1000,
                'Strip',
                10,
                7500,
                9000,
                date('d-m-Y', strtotime('+2 years')),
                'Paracetamol 500mg',
                'Rak A-01',
                'BCH-PCT-001',
            ],
            [
                'OBT-SAMPLE-02',
                'AMOXICILLIN 500MG KAPSUL',
                'Obat Keras',
                20,
                'Box',
                45000,
                5,
                'Kapsul',
                100,
                750,
                950,
                'Strip',
                10,
                7000,
                8500,
                date('d-m-Y', strtotime('+2 years')),
                'Amoxicillin Trihydrate 500mg',
                'Rak B-02',
                'BCH-AMX-001',
            ],
            [
                'OBT-SAMPLE-03',
                'ANTASIDA DOEN SUSPENSI 60ML',
                'Obat Bebas',
                30,
                'Botol',
                6500,
                5,
                'Botol',
                1,
                8500,
                9000,
                '',
                '',
                '',
                '',
                date('d-m-Y', strtotime('+2 years')),
                'Aluminium & Magnesium Hydroxide',
                'Rak C-03',
                'BCH-ANT-001',
            ],
        ];

        $rowIdx = 2;
        foreach ($sampleRows as $data) {
            $colLetter = 'A';
            foreach ($data as $val) {
                $sheet->setCellValueExplicit("{$colLetter}{$rowIdx}", $val, DataType::TYPE_STRING);
                $colLetter++;
            }
            $rowIdx++;
        }

        // Border & Auto width for Sheet 1
        $dataBorder = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheet->getStyle('A2:S' . ($rowIdx - 1))->applyFromArray($dataBorder);

        foreach (range('A', 'S') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        // Sheet 2: Petunjuk Kolom
        $guideSheet = $spreadsheet->createSheet();
        $guideSheet->setTitle('Petunjuk Pengisian');

        $guideSheet->setCellValue('A1', 'Nama Kolom');
        $guideSheet->setCellValue('B1', 'Keterangan & Cara Pengisian');
        $guideSheet->setCellValue('C1', 'Contoh Nilai');
        $guideSheet->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $guideSheet->getRowDimension(1)->setRowHeight(24);

        $guides = [
            ['kode', 'Kode unik SKU / Barcode produk dari pabrik. Jika kosong akan dibuatkan otomatis.', '0101a000512 atau 899123456789'],
            ['nama', 'Nama lengkap obat beserta dosis kekuatan (Wajib diisi).', 'PARACETAMOL 500MG TABLET'],
            ['kategori', 'Golongan regulasi BPOM: Obat Bebas, Obat Bebas Terbatas, Obat Keras, Alkes, Narkotika, Psikotropika, Prekursor.', 'Obat Bebas'],
            ['stok', 'Jumlah fisik awal saat ini dalam hitungan Satuan 1 (atau satuan beli).', '100'],
            ['satuan_beli', 'Kemasan saat membeli ke PBF / distributor.', 'Box / Dos / Botol'],
            ['harga_beli', 'Harga modal beli dari distributor per satuan beli.', '45000'],
            ['stok_min', 'Batas minimal stok sebelum sistem memunculkan peringatan restock.', '10'],
            ['satuan_1', 'Satuan dasar / terkecil saat dijual ke pasien di kasir (Wajib diisi).', 'Tablet / Kapsul / Botol'],
            ['isi_1', 'Faktor konversi: berapa isi satuan 1 di dalam satu satuan_beli.', '100 (jika 1 box = 100 tablet)'],
            ['harga_jual_1', 'Harga jual tunai/eceran umum per satuan 1.', '800'],
            ['harga_resep_1', 'Harga jual khusus resep dokter per satuan 1 (opsional).', '1000'],
            ['satuan_2', 'Satuan tingkat menengah (opsional).', 'Strip'],
            ['isi_2', 'Berapa isi satuan 1 di dalam satu satuan 2 (opsional).', '10 (jika 1 strip = 10 tablet)'],
            ['harga_jual_2', 'Harga jual tunai per satuan 2 (opsional).', '7500'],
            ['harga_resep_2', 'Harga resep per satuan 2 (opsional).', '9000'],
            ['expired', 'Tanggal kedaluwarsa obat format DD-MM-YYYY.', '31-12-2027'],
            ['komposisi', 'Nama zat aktif obat atau indikasi medis.', 'Paracetamol 500mg'],
            ['lokasi', 'Nama rak atau lemari penyimpanan di apotek.', 'Rak A-01 / Lemari Narkotik'],
            ['no_batch', 'Nomor batch resmi dari produsen.', 'BCH-2026-001'],
        ];

        $gIdx = 2;
        foreach ($guides as $g) {
            $guideSheet->setCellValue("A{$gIdx}", $g[0]);
            $guideSheet->setCellValue("B{$gIdx}", $g[1]);
            $guideSheet->setCellValue("C{$gIdx}", $g[2]);
            $gIdx++;
        }
        $guideSheet->getStyle('A2:C' . ($gIdx - 1))->applyFromArray($dataBorder);
        foreach (['A', 'B', 'C'] as $col) {
            $guideSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Set active sheet back to Sheet 1
        $spreadsheet->setActiveSheetIndex(0);

        return response()->stream(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Export all active products to pure Microsoft Excel (.xlsx) file.
     */
    public function exportToXlsx(): StreamedResponse
    {
        $fileName = 'Data_Obat_Apotek_' . date('Ymd_His') . '.xlsx';

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Apotek POS')
            ->setTitle('Master Data Obat Apotek')
            ->setSubject('Export Database Obat');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Master Obat');

        $headers = [
            'A1' => 'kode',
            'B1' => 'nama',
            'C1' => 'kategori',
            'D1' => 'stok',
            'E1' => 'satuan_beli',
            'F1' => 'harga_beli',
            'G1' => 'stok_min',
            'H1' => 'satuan_1',
            'I1' => 'isi_1',
            'J1' => 'harga_jual_1',
            'K1' => 'harga_resep_1',
            'L1' => 'satuan_2',
            'M1' => 'isi_2',
            'N1' => 'harga_jual_2',
            'O1' => 'harga_resep_2',
            'P1' => 'expired',
            'Q1' => 'komposisi',
            'R1' => 'lokasi',
            'S1' => 'no_batch',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '475569']]],
        ];
        $sheet->getStyle('A1:S1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $rowIdx = 2;
        Product::with(['units', 'batches' => function ($q) {
            $q->orderBy('expired_date', 'asc');
        }])->chunk(200, function ($products) use ($sheet, &$rowIdx) {
            foreach ($products as $p) {
                $units = $p->units->sortBy('conversion_factor')->values();
                $unit1 = $units->first();
                $unit2 = $units->get(1);

                $totalStock = $p->batches->sum('stock_base_unit');
                $earliestBatch = $p->batches->first();

                $sheet->setCellValueExplicit("A{$rowIdx}", $p->sku, DataType::TYPE_STRING);
                $sheet->setCellValue("B{$rowIdx}", $p->name);
                $sheet->setCellValue("C{$rowIdx}", $p->drug_class->label());
                $sheet->setCellValue("D{$rowIdx}", $totalStock);
                $sheet->setCellValue("E{$rowIdx}", $unit2 ? $unit2->unit_name : $p->base_unit_name);
                $sheet->setCellValue("F{$rowIdx}", $earliestBatch ? (float) ($earliestBatch->buy_price_base * ($unit2 ? $unit2->conversion_factor : 1)) : 0);
                $sheet->setCellValue("G{$rowIdx}", $p->min_stock_base);
                $sheet->setCellValue("H{$rowIdx}", $unit1 ? $unit1->unit_name : $p->base_unit_name);
                $sheet->setCellValue("I{$rowIdx}", $unit1 ? $unit1->conversion_factor : 1);
                $sheet->setCellValue("J{$rowIdx}", $unit1 ? (float) $unit1->sell_price : 0);
                $sheet->setCellValue("K{$rowIdx}", $unit1 ? (float) ($unit1->recipe_price ?: $unit1->sell_price) : 0);
                $sheet->setCellValue("L{$rowIdx}", $unit2 ? $unit2->unit_name : '');
                $sheet->setCellValue("M{$rowIdx}", $unit2 ? $unit2->conversion_factor : '');
                $sheet->setCellValue("N{$rowIdx}", $unit2 ? (float) $unit2->sell_price : '');
                $sheet->setCellValue("O{$rowIdx}", $unit2 ? (float) ($unit2->recipe_price ?: $unit2->sell_price) : '');
                $sheet->setCellValue("P{$rowIdx}", $earliestBatch ? $earliestBatch->expired_date->format('d-m-Y') : '');
                $sheet->setCellValue("Q{$rowIdx}", $p->generic_name ?? '');
                $sheet->setCellValue("R{$rowIdx}", $p->rack_location ?? '');
                $sheet->setCellValue("S{$rowIdx}", $earliestBatch ? $earliestBatch->batch_number : '');

                $rowIdx++;
            }
        });

        foreach (range('A', 'S') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        return response()->stream(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Import products from an Excel (.xlsx, .xls) or CSV file.
     */
    public function importFile(string $filePath, bool $giveDefaultStock = true): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File tidak ditemukan: {$filePath}");
        }

        // Use PhpSpreadsheet IOFactory to auto-detect and read XLSX, XLS, or CSV
        $reader = IOFactory::createReaderForFile($filePath);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, false);

        if (empty($rows)) {
            throw new \RuntimeException("File kosong atau tidak memiliki data.");
        }

        $rawHeader = array_shift($rows);
        if (empty($rawHeader)) {
            throw new \RuntimeException("Header kolom tidak ditemukan.");
        }

        $header = [];
        foreach ($rawHeader as $idx => $h) {
            $cleaned = preg_replace('/[\x{FEFF}]/u', '', trim((string) $h));
            $header[$idx] = strtolower($cleaned);
        }

        $imported = 0;
        $updated = 0;
        $rowNum = 1;

        foreach ($rows as $data) {
            $rowNum++;
            if (empty($data) || (count($data) === 1 && empty($data[0]))) {
                continue;
            }

            $row = [];
            foreach ($data as $idx => $val) {
                $key = $header[$idx] ?? "col_{$idx}";
                $row[$key] = trim((string) ($val ?? ''));
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

                // 2. Packaging / Purchase Unit (satuan_beli)
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

                    if (!empty($extraUnit) && $extraIsi > 0) {
                        ProductUnit::updateOrCreate(
                            [
                                'product_id' => $product->id,
                                'unit_name' => $extraUnit,
                            ],
                            [
                                'conversion_factor' => (int) round($extraIsi),
                                'sell_price' => $extraPrice > 0 ? $extraPrice : round($hargaJual1 * $extraIsi),
                                'is_default_pos' => false,
                            ]
                        );
                    }
                }

                // 4. Batch FEFO
                ProductBatch::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'batch_number' => $batchNum,
                    ],
                    [
                        'expired_date' => $expiredDate,
                        'stock_base_unit' => $stockBase,
                        'buy_price_per_base' => $buyPriceBase,
                    ]
                );
            });
        }

        return [
            'total_rows' => $rowNum - 1,
            'imported' => $imported,
            'updated' => $updated,
        ];
    }

    /**
     * Backward-compatible CSV template download.
     */
    public function downloadTemplateCsv(): StreamedResponse
    {
        $fileName = 'Template_Import_Obat.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'kode', 'nama', 'kategori', 'stok', 'satuan_beli', 'harga_beli', 'stok_min',
                'satuan_1', 'satuan_2', 'isi_1', 'isi_2', 'harga_jual_1', 'harga_jual_2',
                'expired', 'komposisi', 'lokasi', 'no_batch',
            ]);

            fputcsv($handle, [
                'OBT-SAMPLE-01', 'PARACETAMOL 500MG TAB', 'Obat Bebas', '100', 'Dos', '25000', '10',
                'Tablet', 'Strip', '1', '10', '500', '4500',
                date('d-m-Y', strtotime('+2 years')), 'Paracetamol 500mg', 'Rak A-01', 'BCH-PCT-001',
            ]);

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Backward-compatible CSV export.
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
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'kode', 'nama', 'kategori', 'stok', 'satuan_beli', 'harga_beli', 'stok_min',
                'satuan_1', 'isi_1', 'harga_jual_1', 'harga_resep_1',
                'satuan_2', 'isi_2', 'harga_jual_2', 'harga_resep_2',
                'expired', 'komposisi', 'lokasi', 'no_batch',
            ]);

            Product::with(['units', 'batches' => function ($q) {
                $q->orderBy('expired_date', 'asc');
            }])->chunk(200, function ($products) use ($handle) {
                foreach ($products as $p) {
                    $units = $p->units->sortBy('conversion_factor')->values();
                    $unit1 = $units->first();
                    $unit2 = $units->get(1);

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
                        $unit1 ? $unit1->conversion_factor : 1,
                        $unit1 ? (float) $unit1->sell_price : 0,
                        $unit1 ? (float) ($unit1->recipe_price ?: $unit1->sell_price) : 0,
                        $unit2 ? $unit2->unit_name : '',
                        $unit2 ? $unit2->conversion_factor : '',
                        $unit2 ? (float) $unit2->sell_price : '',
                        $unit2 ? (float) ($unit2->recipe_price ?: $unit2->sell_price) : '',
                        $earliestBatch ? $earliestBatch->expired_date->format('d-m-Y') : '',
                        $p->generic_name ?? '',
                        $p->rack_location ?? '',
                        $earliestBatch ? $earliestBatch->batch_number : '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
