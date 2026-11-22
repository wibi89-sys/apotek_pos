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

    public function export(): StreamedResponse
    {
        return $this->service->exportToCsv();
    }

    public function template(): StreamedResponse
    {
        return $this->service->downloadTemplate();
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xls,xlsx', 'max:20480'], // max 20MB
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        // If user uploaded an .xls or .xlsx, we can process or inform
        if (in_array($ext, ['xls', 'xlsx'], true)) {
            // Check if python conversion script can convert or if it's csv
            // Let's attempt conversion or process
            $tempCsv = storage_path('app/temp_import_' . time() . '.csv');
            $pythonExe = base_path('../hardware-bridge/.venv/Scripts/python.exe');
            if (!file_exists($pythonExe)) $pythonExe = 'python';

            $cmd = sprintf(
                '"%s" -c "import xlrd, csv; wb=xlrd.open_workbook(r\'%s\'); sh=wb.sheet_by_index(0); f=open(r\'%s\', \'w\', newline=\'\', encoding=\'utf-8-sig\'); w=csv.writer(f); [w.writerow([sh.cell_value(r, c) for c in range(sh.ncols)]) for r in range(sh.nrows)]; f.close()"',
                $pythonExe,
                $path,
                $tempCsv
            );
            @shell_exec($cmd);

            if (file_exists($tempCsv)) {
                $path = $tempCsv;
            }
        }

        try {
            $res = $this->service->importFromCsv($path, true);
            $msg = sprintf(
                'Import Excel Berhasil! %s obat baru ditambahkan, %s obat diperbarui dari total %s baris.',
                number_format($res['imported']),
                number_format($res['updated']),
                number_format($res['total_rows'])
            );

            return redirect()->route('products.index')->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->route('products.index')->with('error', 'Gagal impor: ' . $e->getMessage());
        } finally {
            if (isset($tempCsv) && file_exists($tempCsv)) {
                @unlink($tempCsv);
            }
        }
    }
}
