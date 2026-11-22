<?php

declare(strict_types=1);

namespace App\Livewire\Masters;

use App\Models\Supplier;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class SupplierManager extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public ?string $supplierId = null;

    public string $code = '';
    public string $name = '';
    public string $phone = '';
    public string $address = '';
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('suppliers', 'code')->ignore($this->supplierId),
            ],
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->reset(['supplierId', 'code', 'name', 'phone', 'address']);
        $this->code = 'PBF-' . strtoupper(substr(md5((string) microtime()), 0, 4));
        $this->is_active = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function editSupplier(string $id): void
    {
        $sup = Supplier::findOrFail($id);
        $this->supplierId = $sup->id;
        $this->code = $sup->code;
        $this->name = $sup->name;
        $this->phone = $sup->phone ?? '';
        $this->address = $sup->address ?? '';
        $this->is_active = (bool) $sup->is_active;

        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'code' => strtoupper(trim($this->code)),
            'name' => trim($this->name),
            'phone' => !empty($this->phone) ? trim($this->phone) : null,
            'address' => !empty($this->address) ? trim($this->address) : null,
            'is_active' => $this->is_active,
        ];

        if ($this->supplierId) {
            $sup = Supplier::findOrFail($this->supplierId);
            $sup->update($data);
            session()->flash('success', "Distributor {$this->name} berhasil diperbarui.");
        } else {
            Supplier::create($data);
            session()->flash('success', "Distributor PBF baru {$this->name} berhasil ditambahkan.");
        }

        $this->showModal = false;
    }

    public function toggleStatus(string $id): void
    {
        $sup = Supplier::findOrFail($id);
        $sup->is_active = !$sup->is_active;
        $sup->save();

        session()->flash('success', "Status {$sup->name} berhasil diubah.");
    }

    public function render()
    {
        $query = Supplier::query();

        if (trim($this->search) !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('code', 'like', '%' . $this->search . '%')
                    ->orWhere('address', 'like', '%' . $this->search . '%');
            });
        }

        $suppliers = $query->orderBy('name', 'asc')->paginate(10);

        return view('livewire.masters.supplier-manager', [
            'suppliers' => $suppliers,
        ]);
    }
}
