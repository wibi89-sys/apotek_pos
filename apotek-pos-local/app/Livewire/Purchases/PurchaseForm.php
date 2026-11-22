<?php

declare(strict_types=1);

namespace App\Livewire\Purchases;

use App\Models\Product;
use App\Models\Supplier;
use App\Services\Purchases\PurchaseReceiptService;
use Livewire\Component;

class PurchaseForm extends Component
{
    public string $invoiceNo = '';
    public string $supplierId = '';
    public string $purchaseDate = '';
    public string $dueDate = '';
    public string $notes = '';

    public array $items = [];

    public function mount(): void
    {
        $this->purchaseDate = now()->toDateString();
        $this->dueDate = now()->addDays(30)->toDateString();
        $this->addItem();
    }

    public function addItem(): void
    {
        $this->items[] = [
            'product_id' => '',
            'batch_number' => '',
            'expired_date' => now()->addYears(2)->toDateString(),
            'unit_name' => 'Box',
            'conversion_factor' => 100,
            'qty_input' => 1,
            'buy_price_unit' => 50000,
        ];
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    public function updatedItems($value, $key): void
    {
        // When product is selected, auto-fill unit if available
        if (str_ends_with($key, '.product_id')) {
            $parts = explode('.', $key);
            $index = (int) $parts[0];
            $productId = $this->items[$index]['product_id'];

            if ($productId) {
                $product = Product::with('units')->find($productId);
                if ($product && $product->units->isNotEmpty()) {
                    $unit = $product->units->last(); // Usually largest unit e.g. Box
                    $this->items[$index]['unit_name'] = $unit->unit_name;
                    $this->items[$index]['conversion_factor'] = $unit->conversion_factor;
                }
            }
        }
    }

    public function save(PurchaseReceiptService $receiptService): void
    {
        $this->validate([
            'invoiceNo' => ['required', 'string', 'unique:purchases,invoice_no'],
            'supplierId' => ['required', 'string'],
            'purchaseDate' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string'],
            'items.*.batch_number' => ['required', 'string'],
            'items.*.expired_date' => ['required', 'date', 'after:today'],
            'items.*.unit_name' => ['required', 'string'],
            'items.*.conversion_factor' => ['required', 'integer', 'min:1'],
            'items.*.qty_input' => ['required', 'numeric', 'min:0.01'],
            'items.*.buy_price_unit' => ['required', 'numeric', 'min:0'],
        ]);

        $subtotal = '0.00';
        foreach ($this->items as $item) {
            $lineSubtotal = bcmul((string) $item['buy_price_unit'], (string) $item['qty_input'], 2);
            $subtotal = bcadd($subtotal, $lineSubtotal, 2);
        }

        $receiptService->recordReceipt(
            invoiceData: [
                'invoice_no' => $this->invoiceNo,
                'supplier_id' => $this->supplierId,
                'purchase_date' => $this->purchaseDate,
                'due_date' => $this->dueDate ?: null,
                'subtotal' => $subtotal,
                'tax_amount' => '0.00',
                'grand_total' => $subtotal,
                'payment_status' => 'paid',
                'notes' => $this->notes,
            ],
            itemsData: $this->items,
            userId: auth()->id()
        );

        session()->flash('success', "Faktur pembelian #{$this->invoiceNo} berhasil dicatat & stok bertambah sesuai nomor batch!");
        $this->redirectRoute('products.index');
    }

    public function render()
    {
        return view('livewire.purchases.purchase-form', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => 'Input Faktur Pembelian PBF']);
    }
}
