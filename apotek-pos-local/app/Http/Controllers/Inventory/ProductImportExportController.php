<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\ProductImportExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportExportController extends Controller
{
    public function __construct(
        protected ProductImportExportService $service
    ) {}

    /**
     * Export all products to pure Microsoft Excel (.xlsx).
     */
    public function export(): StreamedResponse
    {
        return $this->service->exportToXlsx();
    }

    /**
     * Export all products to CSV.
     */
    public function exportCsv(): StreamedResponse
    {
        return $this->service->exportToCsv();
    }

    /**
     * Download pure Microsoft Excel (.xlsx) template.
     */
    public function template(): StreamedResponse
    {
        return $this->service->downloadTemplateXlsx();
    }

    /**
     * Download CSV template.
     */
    public function templateCsv(): StreamedResponse
    {
        return $this->service->downloadTemplateCsv();
    }

    /**
     * Import products from uploaded Excel (.xlsx, .xls) or CSV.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:25600'], // max 25MB
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        try {
            $res = $this->service->importFile($path, true);
            $msg = sprintf(
                'Import Excel Berhasil! %s obat baru ditambahkan, %s obat diperbarui dari total %s baris.',
                number_format($res['imported']),
                number_format($res['updated']),
                number_format($res['total_rows'])
            );

            return redirect()->route('products.index')->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->route('products.index')->with('error', 'Gagal impor file: ' . $e->getMessage());
        }
    }
}
