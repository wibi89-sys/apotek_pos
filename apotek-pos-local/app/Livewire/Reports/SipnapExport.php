<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Enums\DrugClass;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\SystemMeta;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.app')]
class SipnapExport extends Component
{
    public int $reportingMonth;
    public int $reportingYear;
    public string $selectedDrugCategory = 'psikotropika'; // psikotropika, keras, narkotika

    public function mount(): void
    {
        $this->reportingMonth = (int) now()->format('n');
        $this->reportingYear = (int) now()->format('Y');
    }

    public function downloadCsv(): StreamedResponse
    {
        $month = $this->reportingMonth;
        $year = $this->reportingYear;
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $pharmacySia = SystemMeta::get('pharmacy_sia', 'SIA-442/SIA/2026');
        $pharmacyName = SystemMeta::get('pharmacy_name', 'APOTEK POS HYBRID');

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="SIPNAP_%s_%04d_%02d.csv"', $this->selectedDrugCategory, $year, $month),
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($startDate, $endDate, $pharmacySia, $pharmacyName) {
            $handle = fopen('php://output', 'w');

            // SIPNAP standard header columns
            fputcsv($handle, [
                'KODE_SARANA_SIA',
                'NAMA_APOTEK',
                'KODE_OBAT',
                'NAMA_OBAT',
                'GOLONGAN_OBAT',
                'SATUAN_TERKECIL',
                'STOK_AWAL',
                'PEMASUKAN_PBF',
                'PENGELUARAN_RESEP',
                'STOK_AKHIR',
                'PERIODE_BULAN',
                'PERIODE_TAHUN',
            ]);

            // Query medicines under regulated drug classes (Keras/Psikotropika/Narkotika)
            $products = Product::where('drug_class', DrugClass::KERAS)->with('batches')->get();

            foreach ($products as $product) {
                $batchIds = $product->batches->pluck('id');

                $inQty = (int) StockMovement::whereIn('batch_id', $batchIds)
                    ->where('qty_change_base', '>', 0)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('qty_change_base');

                $outQty = (int) abs((int) StockMovement::whereIn('batch_id', $batchIds)
                    ->where('qty_change_base', '<', 0)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('qty_change_base'));

                $currentStock = (int) $product->batches->sum('stock_base_unit');
                $openingStock = max(0, $currentStock - $inQty + $outQty);

                fputcsv($handle, [
                    $pharmacySia,
                    $pharmacyName,
                    $product->sku,
                    $product->name,
                    $product->drug_class->label(),
                    $product->base_unit_name,
                    $openingStock,
                    $inQty,
                    $outQty,
                    $currentStock,
                    $startDate->format('m'),
                    $startDate->format('Y'),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function render()
    {
        $products = Product::where('drug_class', DrugClass::KERAS)
            ->with('batches')
            ->take(15)
            ->get();

        return view('livewire.reports.sipnap-export', [
            'sampleProducts' => $products,
        ]);
    }
}
