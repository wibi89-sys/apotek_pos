<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ProfitAnalytics extends Component
{
    public string $startDate;
    public string $endDate;

    public function mount(): void
    {
        $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->endDate = Carbon::now()->format('Y-m-d');
    }

    public function render()
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        $transactions = Transaction::whereBetween('created_at', [$start, $end])
            ->where('status', 'completed')
            ->get();

        $totalRevenue = $transactions->sum(fn($t) => (float) $t->grand_total);

        // Sum HPP from transaction items
        $items = TransactionItem::whereHas('transaction', function ($q) use ($start, $end) {
            $q->whereBetween('created_at', [$start, $end])->where('status', 'completed');
        })->get();

        $totalHpp = $items->sum(fn($item) => (float) bcmul((string) $item->hpp_base_snapshot, (string) $item->qty_base_deducted, 2));
        $grossProfit = $totalRevenue - $totalHpp;
        $profitMargin = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0.0;

        // Top profitable products
        $topProducts = TransactionItem::with('product')
            ->select('product_id', DB::raw('SUM(subtotal) as revenue'), DB::raw('SUM(qty_base_deducted * hpp_base_snapshot) as cost'))
            ->whereHas('transaction', function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end])->where('status', 'completed');
            })
            ->groupBy('product_id')
            ->orderByRaw('(SUM(subtotal) - SUM(qty_base_deducted * hpp_base_snapshot)) DESC')
            ->take(10)
            ->get();

        return view('livewire.reports.profit-analytics', [
            'totalRevenue' => $totalRevenue,
            'totalHpp' => $totalHpp,
            'grossProfit' => $grossProfit,
            'profitMargin' => $profitMargin,
            'topProducts' => $topProducts,
            'transactionCount' => $transactions->count(),
        ]);
    }
}
