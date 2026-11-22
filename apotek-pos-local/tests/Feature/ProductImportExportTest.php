<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DrugClass;
use App\Http\Middleware\VerifyLicenseMiddleware;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProductImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([VerifyLicenseMiddleware::class]);
    }

    public function test_can_download_pure_xlsx_template(): void
    {
        $response = $this->get(route('products.template'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('Template_Import_Obat.xlsx', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_can_download_csv_template(): void
    {
        $response = $this->get(route('products.template.csv'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Template_Import_Obat.csv', (string) $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('kode,nama,kategori,stok,satuan_beli', $content);
        $this->assertStringContainsString('Paracetamol 500mg', $content);
    }

    public function test_can_export_products_to_pure_xlsx(): void
    {
        $p = Product::create([
            'sku' => 'MED-TEST-001',
            'name' => 'Amoxicillin 500mg Kapsul',
            'generic_name' => 'Amoxicillin',
            'drug_class' => DrugClass::KERAS,
            'base_unit_name' => 'Kapsul',
            'min_stock_base' => 20,
        ]);

        ProductUnit::create([
            'product_id' => $p->id,
            'unit_name' => 'Kapsul',
            'conversion_factor' => 1,
            'buy_price' => '800.00',
            'sell_price' => '1200.00',
            'recipe_price' => '1500.00',
            'is_default_pos' => true,
        ]);

        ProductBatch::create([
            'product_id' => $p->id,
            'batch_number' => 'BATCH-AMX-99',
            'expired_date' => now()->addMonths(18)->format('Y-m-d'),
            'stock_base_unit' => 100,
            'buy_price_per_base' => '800.00',
        ]);

        $response = $this->get(route('products.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('Data_Obat_Apotek_', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_can_export_products_to_csv(): void
    {
        $p = Product::create([
            'sku' => 'MED-TEST-002',
            'name' => 'Paracetamol 500mg Tablet',
            'generic_name' => 'Paracetamol',
            'drug_class' => DrugClass::BEBAS,
            'base_unit_name' => 'Tablet',
            'min_stock_base' => 10,
        ]);

        ProductUnit::create([
            'product_id' => $p->id,
            'unit_name' => 'Tablet',
            'conversion_factor' => 1,
            'buy_price' => '300.00',
            'sell_price' => '500.00',
            'recipe_price' => '600.00',
            'is_default_pos' => true,
        ]);

        $response = $this->get(route('products.export.csv'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('.csv', (string) $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('MED-TEST-002', $content);
        $this->assertStringContainsString('Paracetamol 500mg Tablet', $content);
    }

    public function test_can_import_products_via_web_upload_csv(): void
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, [
            'kode', 'nama', 'kategori', 'stok', 'satuan_beli', 'harga_beli', 'stok_min',
            'satuan_1', 'satuan_2', 'isi_1', 'isi_2', 'harga_jual_1', 'harga_jual_2',
            'expired', 'komposisi', 'lokasi', 'no_batch',
        ]);
        fputcsv($stream, [
            'OBT001', 'Ibuprofen 400mg', 'Obat Bebas Terbatas', '10', 'Box', '50000', '5',
            'Tablet', 'Strip', '1', '10', '800', '7500',
            '15-12-2027', 'Ibuprofen 400mg', 'Rak A1', 'BATCH-IBU-01',
        ]);
        fputcsv($stream, [
            'OBT002', 'Cetirizine 10mg', 'Obat Keras', '20', 'Strip', '12000', '2',
            'Tablet', '', '1', '', '1800', '',
            '10-06-2028', 'Cetirizine 10mg', 'Rak B2', 'BATCH-CTZ-02',
        ]);
        rewind($stream);
        $fileContent = "\xEF\xBB\xBF" . stream_get_contents($stream);
        fclose($stream);

        $uploadedFile = UploadedFile::fake()->createWithContent('master_obat_baru.csv', $fileContent);

        $response = $this->post(route('products.import'), [
            'file' => $uploadedFile,
        ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'sku' => 'OBT001',
            'name' => 'Ibuprofen 400mg',
            'base_unit_name' => 'Tablet',
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'OBT002',
            'name' => 'Cetirizine 10mg',
            'drug_class' => DrugClass::KERAS->value,
        ]);

        $this->assertDatabaseHas('product_batches', [
            'batch_number' => 'BATCH-IBU-01',
            'stock_base_unit' => 10,
        ]);

        $this->assertDatabaseHas('product_units', [
            'unit_name' => 'Strip',
            'conversion_factor' => 10,
        ]);
    }

    public function test_can_import_products_via_pure_xlsx_file(): void
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray([
            ['kode', 'nama', 'kategori', 'stok', 'satuan_beli', 'harga_beli', 'stok_min', 'satuan_1', 'isi_1', 'harga_jual_1', 'satuan_2', 'isi_2', 'harga_jual_2', 'expired', 'komposisi', 'lokasi', 'no_batch'],
            ['OBT-XLSX-01', 'Omeprazole 20mg', 'Obat Keras', 50, 'Box', 35000, 5, 'Kapsul', 30, 1500, 'Strip', 10, 14000, '25-12-2028', 'Omeprazole 20mg', 'Rak G1', 'BCH-OMZ-01'],
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'test_xlsx_') . '.xlsx';
        $writer = new Xlsx($ss);
        $writer->save($tempPath);

        $uploadedFile = new UploadedFile($tempPath, 'master_obat.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->post(route('products.import'), [
            'file' => $uploadedFile,
        ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'sku' => 'OBT-XLSX-01',
            'name' => 'Omeprazole 20mg',
            'drug_class' => DrugClass::KERAS->value,
            'base_unit_name' => 'Kapsul',
        ]);

        $this->assertDatabaseHas('product_units', [
            'unit_name' => 'Strip',
            'conversion_factor' => 10,
        ]);

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
    }
}
