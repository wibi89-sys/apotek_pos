<?php

declare(strict_types=1);

namespace App\Livewire\Inventory;

use App\Enums\DrugClass;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ProductForm extends Component
{
    public string $name = '';
    public string $genericName = '';
    public string $sku = '';
    public string $barcode = '';
    public string $drugClass = 'bebas';
    public string $category = '';
    public string $rackLocation = '';
    public string $baseUnitName = 'Tablet';
    public int $minStockBase = 10;

    // Multi-tier units
    public array $units = [
        ['unit_name' => 'Tablet', 'conversion_factor' => 1, 'barcode_unit' => '', 'sell_price' => '1000', 'is_default_pos' => true],
        ['unit_name' => 'Strip', 'conversion_factor' => 10, 'barcode_unit' => '', 'sell_price' => '9500', 'is_default_pos' => false],
    ];

    public function addUnit(): void
    {
        $this->units[] = [
            'unit_name' => '',
            'conversion_factor' => 1,
            'barcode_unit' => '',
            'sell_price' => '0',
            'is_default_pos' => false,
        ];
    }

    public function removeUnit(int $index): void
    {
        if (count($this->units) > 1) {
            unset($this->units[$index]);
            $this->units = array_values($this->units);
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'drugClass' => ['required', 'string'],
            'baseUnitName' => ['required', 'string', 'max:50'],
            'units' => ['required', 'array', 'min:1'],
            'units.*.unit_name' => ['required', 'string', 'max:50'],
            'units.*.conversion_factor' => ['required', 'integer', 'min:1'],
            'units.*.sell_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () {
            $product = Product::create([
                'name' => $this->name,
                'generic_name' => $this->genericName ?: null,
                'sku' => $this->sku,
                'barcode' => $this->barcode ?: null,
                'drug_class' => DrugClass::from($this->drugClass),
                'category' => $this->category ?: null,
                'rack_location' => $this->rackLocation ?: null,
                'base_unit_name' => $this->baseUnitName,
                'min_stock_base' => $this->minStockBase,
                'is_active' => true,
            ]);

            foreach ($this->units as $u) {
                ProductUnit::create([
                    'product_id' => $product->id,
                    'unit_name' => $u['unit_name'],
                    'conversion_factor' => (int) $u['conversion_factor'],
                    'barcode_unit' => $u['barcode_unit'] ?: null,
                    'sell_price' => (string) $u['sell_price'],
                    'is_default_pos' => (bool) ($u['is_default_pos'] ?? false),
                ]);
            }
        });

        session()->flash('success', "Obat '{$this->name}' berhasil ditambahkan dengan multi-satuan!");
        $this->redirectRoute('products.index');
    }

    public function render()
    {
        return view('livewire.inventory.product-form', [
            'drugClasses' => DrugClass::cases(),
        ])->layout('components.layouts.app', ['title' => 'Tambah Obat Baru']);
    }
}
