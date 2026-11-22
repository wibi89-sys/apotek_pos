<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Models\SystemMeta;
use App\Models\Transaction;

class ReceiptPayloadBuilderService
{
    public function build(Transaction $transaction, int $paperWidth = 58, bool $openDrawer = true): array
    {
        $pharmacyName = SystemMeta::get('pharmacy_name', 'APOTEK POS HYBRID');
        $address = SystemMeta::get('pharmacy_address', 'Jl. Sehat Farmasi No. 1');
        $phone = SystemMeta::get('pharmacy_phone', '0812-3456-7890');
        $siaNumber = SystemMeta::get('pharmacy_sia', 'SIA: 442/SIA/2026');
        $apaName = SystemMeta::get('pharmacy_apa', 'Apt. Pengelola, S.Farm');
        $sipaNumber = SystemMeta::get('pharmacy_sipa', 'SIPA: 1990/SIPA/2026');
        $footerText = SystemMeta::get('receipt_footer', "Terima kasih atas kunjungan Anda.\nSemoga Lekas Sembuh!");

        $items = [];
        foreach ($transaction->items as $item) {
            $unitName = $item->productUnit?->unit_name ?? $item->product->base_unit_name;
            $items[] = [
                'name' => $item->product->name,
                'qty' => (string) (float) $item->qty_input,
                'unit' => $unitName,
                'price' => number_format((float) $item->unit_price, 0, ',', '.'),
                'subtotal' => number_format((float) $item->subtotal, 0, ',', '.'),
                'is_racikan' => $item->is_racikan,
                'racikan_group' => $item->racikan_group_name,
            ];
        }

        return [
            'paper_width' => $paperWidth,
            'open_drawer' => $openDrawer,
            'header' => [
                'pharmacy_name' => $pharmacyName,
                'address' => $address,
                'phone' => $phone,
                'sia_number' => $siaNumber,
                'apa_name' => $apaName,
                'sipa_number' => $sipaNumber,
            ],
            'transaction' => [
                'invoice_no' => $transaction->invoice_no,
                'date' => $transaction->created_at->format('d/m/Y H:i:s'),
                'cashier_name' => $transaction->cashier->name,
                'terminal_id' => $transaction->terminal_id,
                'type' => $transaction->type->label(),
                'patient_name' => $transaction->patient_name,
                'doctor_name' => $transaction->doctor_name,
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => number_format((float) $transaction->subtotal, 0, ',', '.'),
                'tuslah_embalase' => number_format((float) $transaction->tuslah_embalase_total, 0, ',', '.'),
                'discount' => number_format((float) $transaction->discount_total, 0, ',', '.'),
                'tax' => number_format((float) $transaction->tax_total, 0, ',', '.'),
                'grand_total' => number_format((float) $transaction->grand_total, 0, ',', '.'),
                'payment_method' => $transaction->payment_method->label(),
                'paid_amount' => number_format((float) $transaction->paid_amount, 0, ',', '.'),
                'change_amount' => number_format((float) $transaction->change_amount, 0, ',', '.'),
            ],
            'footer' => $footerText,
        ];
    }
}
