<?php

declare(strict_types=1);

namespace App\Livewire\Inventory;

use App\Enums\DrugClass;
use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;

class ProductList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $selectedDrugClass = '';
    public int $perPage = 15;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedDrugClass(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Product::with(['units', 'batches' => function ($q) {
            $q->where('stock_base_unit', '>', 0)
                ->where('expired_date', '>', now()->toDateString())
                ->orderBy('expired_date', 'asc');
        }])->where('is_active', true);

        if (! empty($this->selectedDrugClass)) {
            $query->where('drug_class', $this->selectedDrugClass);
        }

        if (trim($this->search) !== '') {
            $query->search($this->search);
        }

        $products = $query->paginate($this->perPage);

        return view('livewire.inventory.product-list', [
            'products' => $products,
            'drugClasses' => DrugClass::cases(),
        ])->layout('components.layouts.app', ['title' => 'Master Obat & Inventori']);
    }
}
