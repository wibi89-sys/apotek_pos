<?php

declare(strict_types=1);

namespace App\Livewire\Masters;

use App\Models\Doctor;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class DoctorManager extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public ?string $doctorId = null;

    public string $name = '';
    public string $sip_number = '';
    public string $specialization = '';
    public string $clinic_name = '';
    public string $phone = '';
    public string $address = '';
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'sip_number' => ['nullable', 'string', 'max:100'],
            'specialization' => ['nullable', 'string', 'max:100'],
            'clinic_name' => ['nullable', 'string', 'max:150'],
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
        $this->reset(['doctorId', 'name', 'sip_number', 'specialization', 'clinic_name', 'phone', 'address']);
        $this->is_active = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function editDoctor(string $id): void
    {
        $doc = Doctor::findOrFail($id);
        $this->doctorId = $doc->id;
        $this->name = $doc->name;
        $this->sip_number = $doc->sip_number ?? '';
        $this->specialization = $doc->specialization ?? '';
        $this->clinic_name = $doc->clinic_name ?? '';
        $this->phone = $doc->phone ?? '';
        $this->address = $doc->address ?? '';
        $this->is_active = (bool) $doc->is_active;

        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'sip_number' => !empty($this->sip_number) ? $this->sip_number : null,
            'specialization' => !empty($this->specialization) ? $this->specialization : null,
            'clinic_name' => !empty($this->clinic_name) ? $this->clinic_name : null,
            'phone' => !empty($this->phone) ? $this->phone : null,
            'address' => !empty($this->address) ? $this->address : null,
            'is_active' => $this->is_active,
        ];

        if ($this->doctorId) {
            $doc = Doctor::findOrFail($this->doctorId);
            $doc->update($data);
            session()->flash('success', "Data {$this->name} berhasil diperbarui.");
        } else {
            Doctor::create($data);
            session()->flash('success', "Dokter baru {$this->name} berhasil ditambahkan.");
        }

        $this->showModal = false;
    }

    public function toggleStatus(string $id): void
    {
        $doc = Doctor::findOrFail($id);
        $doc->is_active = !$doc->is_active;
        $doc->save();

        session()->flash('success', "Status {$doc->name} berhasil diubah.");
    }

    public function render()
    {
        $query = Doctor::query();

        if (trim($this->search) !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('sip_number', 'like', '%' . $this->search . '%')
                    ->orWhere('clinic_name', 'like', '%' . $this->search . '%')
                    ->orWhere('specialization', 'like', '%' . $this->search . '%');
            });
        }

        $doctors = $query->orderBy('name', 'asc')->paginate(10);

        return view('livewire.masters.doctor-manager', [
            'doctors' => $doctors,
        ]);
    }
}
