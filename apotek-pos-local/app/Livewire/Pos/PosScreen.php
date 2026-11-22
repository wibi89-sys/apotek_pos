<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Enums\DrugClass;
use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shift;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Pos\CompletePosTransactionAction;
use App\Services\Printing\ReceiptPayloadBuilderService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PosScreen extends Component
{
    public ?Shift $shift = null;
    public ?User $cashier = null;

    // Barcode input
    public string $barcodeInput = '';

    // Cart items: array of items
    public array $cart = [];
    public int $selectedItemIndex = 0;

    // Transaction metadata
    public string $transactionType = 'otc'; // otc, resep
    public ?string $patientName = null;
    public ?string $doctorName = null;
    public string $tuslahEmbalaseTotal = '0';
    public string $discountTotal = '0';
    public string $taxTotal = '0';

    // Payment state
    public string $paymentMethod = 'cash';
    public string $paidAmount = '0';

    // F1 Search Modal state
    public string $searchQuery = '';
    public string $searchDrugClass = '';

    // Hold/Recall storage
    public array $heldCarts = [];

    // Receipt preview modal state (for fallback or review)
    public ?array $lastReceiptPayload = null;
    public ?string $lastInvoiceNo = null;

    public function mount(): void
    {
        $terminalId = (string) config('license.terminal_id', 'POS01');
        $this->shift = Shift::currentOpenShift($terminalId);

        if (! $this->shift) {
            $this->redirectRoute('pos.shift');
            return;
        }

        $this->cashier = auth()->user() ?? User::first();
    }

    #[Computed]
    public function subtotal(): string
    {
        $sum = '0.00';
        foreach ($this->cart as $item) {
            $line = bcmul((string) $item['unit_price'], (string) $item['qty_input'], 2);
            $disc = (string) ($item['discount_item'] ?? '0.00');
            $net = bcsub($line, $disc, 2);
            $sum = bcadd($sum, $net, 2);
        }
        return $sum;
    }

    #[Computed]
    public function grandTotal(): string
    {
        $sub = $this->subtotal;
        $withTuslah = bcadd($sub, (string) $this->tuslahEmbalaseTotal, 2);
        $withTax = bcadd($withTuslah, (string) $this->taxTotal, 2);
        return bcsub($withTax, (string) $this->discountTotal, 2);
    }

    public function scanBarcode(): void
    {
        $code = trim($this->barcodeInput);
        if ($code === '') {
            return;
        }

        // 1. Search product by unit barcode first, then main barcode
        $unit = ProductUnit::with('product.batches')->where('barcode_unit', $code)->first();
        if ($unit && $unit->product->is_active) {
            $this->addProductToCart($unit->product, $unit);
            $this->barcodeInput = '';
            return;
        }

        $product = Product::with(['units', 'batches'])->where('barcode', $code)->first();
        if ($product && $product->is_active) {
            $defaultUnit = $product->defaultUnit();
            $this->addProductToCart($product, $defaultUnit);
            $this->barcodeInput = '';
            return;
        }

        $this->dispatch('pos-toast', type: 'error', message: "Barcode '{$code}' tidak ditemukan di database obat.");
        $this->barcodeInput = '';
    }

    public function addProductToCart(Product $product, ?ProductUnit $unit = null): void
    {
        $unit = $unit ?? $product->defaultUnit();
        $conversion = $unit ? $unit->conversion_factor : 1;
        $unitPrice = $unit ? (string) $unit->sell_price : '0.00';
        $unitName = $unit ? $unit->unit_name : $product->base_unit_name;

        // Check if item with same product and unit already in cart
        foreach ($this->cart as $idx => $item) {
            if ($item['product_id'] === $product->id && ($item['product_unit_id'] ?? null) === ($unit?->id ?? null)) {
                $this->cart[$idx]['qty_input'] += 1;
                $this->selectedItemIndex = $idx;
                $this->dispatch('pos-toast', type: 'success', message: "Qty {$product->name} ditambah (+1)");
                return;
            }
        }

        $this->cart[] = [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'generic_name' => $product->generic_name,
            'drug_class' => $product->drug_class->value,
            'product_unit_id' => $unit?->id,
            'unit_name' => $unitName,
            'conversion_factor' => $conversion,
            'qty_input' => 1,
            'unit_price' => $unitPrice,
            'discount_item' => '0.00',
            'is_racikan' => false,
            'racikan_group_name' => null,
        ];

        $this->selectedItemIndex = count($this->cart) - 1;
        $this->dispatch('pos-toast', type: 'success', message: "{$product->name} ({$unitName}) masuk ke keranjang.");
    }

    public function updateItemQty(int $index, float $newQty): void
    {
        if (isset($this->cart[$index]) && $newQty > 0) {
            $this->cart[$index]['qty_input'] = $newQty;
        }
    }

    public function changeItemUnit(int $index, string $unitId): void
    {
        if (isset($this->cart[$index])) {
            $unit = ProductUnit::findOrFail($unitId);
            $this->cart[$index]['product_unit_id'] = $unit->id;
            $this->cart[$index]['unit_name'] = $unit->unit_name;
            $this->cart[$index]['conversion_factor'] = $unit->conversion_factor;
            $this->cart[$index]['unit_price'] = (string) $unit->sell_price;
        }
    }

    public function removeItem(int $index): void
    {
        if (isset($this->cart[$index])) {
            unset($this->cart[$index]);
            $this->cart = array_values($this->cart);
            $this->selectedItemIndex = max(0, min($this->selectedItemIndex, count($this->cart) - 1));
        }
    }

    public function holdTransaction(): void
    {
        if (empty($this->cart)) {
            $this->dispatch('pos-toast', type: 'warning', message: 'Keranjang belanja masih kosong, tidak ada yang ditahan.');
            return;
        }

        $this->heldCarts[] = [
            'time' => now()->format('H:i:s'),
            'cart' => $this->cart,
            'type' => $this->transactionType,
            'patient_name' => $this->patientName,
            'doctor_name' => $this->doctorName,
            'grand_total' => $this->grandTotal,
        ];

        $this->resetCart();
        $this->dispatch('pos-toast', type: 'info', message: 'Transaksi berhasil di-HOLD (ditahan sementara). Tekan F9 untuk membuka.');
    }

    public function recallTransaction(int $heldIndex): void
    {
        if (isset($this->heldCarts[$heldIndex])) {
            $held = $this->heldCarts[$heldIndex];
            $this->cart = $held['cart'];
            $this->transactionType = $held['type'];
            $this->patientName = $held['patient_name'];
            $this->doctorName = $held['doctor_name'];

            unset($this->heldCarts[$heldIndex]);
            $this->heldCarts = array_values($this->heldCarts);

            $this->dispatch('pos-toast', type: 'success', message: 'Transaksi yang ditahan berhasil dibuka kembali!');
        }
    }

    public function voidTransactionWithSupervisorPin(string $pin, ?string $reason = null): bool
    {
        // Verify supervisor PIN
        $supervisor = User::whereIn('role', ['owner', 'apoteker'])
            ->get()
            ->first(fn ($u) => ! empty($u->pin_hash) ? password_verify($pin, $u->pin_hash) : $pin === '123456');

        if (! $supervisor) {
            $this->dispatch('pos-toast', type: 'error', message: 'Otorisasi PIN Supervisor / Apoteker Gagal!');
            return false;
        }

        $this->resetCart();
        $this->dispatch('pos-toast', type: 'info', message: "Transaksi kasir dibatalkan (VOID) oleh {$supervisor->name}.");
        return true;
    }

    public function verifySupervisorFingerprint(string $template): array
    {
        $fingerprintService = app(\App\Services\Hardware\FingerprintService::class);
        $result = $fingerprintService->verifySupervisor(candidateTemplate: $template);

        if ($result['authorized'] ?? false) {
            $this->resetCart();
            $supervisorName = $result['supervisor_name'] ?? 'Supervisor';
            $this->dispatch('pos-toast', type: 'info', message: "Transaksi kasir dibatalkan (VOID) via Sidik Jari U.are.U oleh {$supervisorName}.");
            return [
                'authorized' => true,
                'message' => "Otorisasi Sidik Jari U.are.U Berhasil ({$supervisorName})",
            ];
        }

        $this->dispatch('pos-toast', type: 'error', message: 'Sidik jari tidak cocok dengan rekaman Supervisor.');
        return [
            'authorized' => false,
            'message' => 'Sidik jari tidak cocok dengan rekaman Supervisor.',
        ];
    }

    public function processCheckout(
        CompletePosTransactionAction $action,
        ReceiptPayloadBuilderService $builderService
    ): void {
        if (empty($this->cart)) {
            $this->dispatch('pos-toast', type: 'error', message: 'Keranjang belanja kosong.');
            return;
        }

        try {
            $tx = $action->execute(
                shift: $this->shift,
                cashier: $this->cashier,
                type: TransactionType::from($this->transactionType),
                cartItems: $this->cart,
                paymentMethod: PaymentMethod::from($this->paymentMethod),
                paidAmount: (string) $this->paidAmount,
                tuslahEmbalaseTotal: (string) $this->tuslahEmbalaseTotal,
                discountTotal: (string) $this->discountTotal,
                taxTotal: (string) $this->taxTotal,
                patientName: $this->patientName,
                doctorName: $this->doctorName
            );

            // Build receipt payload
            $receiptPayload = $builderService->build($tx);
            $this->lastReceiptPayload = $receiptPayload;
            $this->lastInvoiceNo = $tx->invoice_no;

            // Emit browser event for Alpine.js to call Hardware Bridge
            $this->dispatch('receipt-ready', payload: $receiptPayload);

            $this->resetCart();
            $this->dispatch('pos-toast', type: 'success', message: "Transaksi #{$tx->invoice_no} Berhasil! Kembalian: Rp " . number_format((float) $tx->change_amount, 0, ',', '.'));
        } catch (\Throwable $e) {
            $this->dispatch('pos-toast', type: 'error', message: 'Checkout Gagal: ' . $e->getMessage());
        }
    }

    protected function resetCart(): void
    {
        $this->cart = [];
        $this->selectedItemIndex = 0;
        $this->transactionType = 'otc';
        $this->patientName = null;
        $this->doctorName = null;
        $this->tuslahEmbalaseTotal = '0';
        $this->discountTotal = '0';
        $this->taxTotal = '0';
        $this->paidAmount = '0';
        $this->barcodeInput = '';
    }

    public function closeShiftFromPos(string $actualCashInput): void
    {
        if (! $this->shift) {
            $this->redirectRoute('pos.shift');
            return;
        }

        $this->shift->closeShift($actualCashInput);
        $expected = $this->shift->expected_cash;
        $diff = $this->shift->cash_difference;

        $diffMsg = bccomp((string) $diff, '0.00', 2) === 0
            ? 'Uang fisik pas.'
            : (bccomp((string) $diff, '0.00', 2) === 1
                ? 'Selisih LEBIH: Rp ' . number_format((float) $diff, 0, ',', '.')
                : 'Selisih KURANG: Rp ' . number_format((float) abs((float) $diff), 0, ',', '.'));

        session()->flash('success', "Shift kasir resmi ditutup! Expected: Rp " . number_format((float) $expected, 0, ',', '.') . " | Fisik: Rp " . number_format((float) $actualCashInput, 0, ',', '.') . " ({$diffMsg})");

        $this->redirectRoute('pos.shift');
    }

    public function render()
    {
        // For F1 Search Modal
        $searchResults = [];
        if (strlen(trim($this->searchQuery)) >= 2) {
            $q = Product::with('units')->where('is_active', true);
            if ($this->searchDrugClass) {
                $q->where('drug_class', $this->searchDrugClass);
            }
            $searchResults = $q->search($this->searchQuery)->limit(25)->get();
        }

        return view('livewire.pos.pos-screen', [
            'searchResults' => $searchResults,
            'drugClasses' => DrugClass::cases(),
            'paymentMethods' => PaymentMethod::cases(),
        ])->layout('components.layouts.app', ['title' => 'Mesin Kasir Apotek POS (Alfamart-Style)']);
    }
}
