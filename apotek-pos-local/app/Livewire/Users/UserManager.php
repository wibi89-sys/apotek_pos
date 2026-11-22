<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class UserManager extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public ?string $userId = null;

    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $role = 'kasir';
    public string $password = '';
    public string $pin = '';
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($this->userId),
            ],
            'email' => [
                'nullable',
                'email',
                Rule::unique('users', 'email')->ignore($this->userId),
            ],
            'role' => ['required', Rule::in(['kasir', 'apoteker', 'owner'])],
            'password' => [$this->userId ? 'nullable' : 'required', 'string', 'min:6'],
            'pin' => ['nullable', 'string', 'digits:6'],
            'is_active' => ['boolean'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->reset(['userId', 'name', 'username', 'email', 'password', 'pin']);
        $this->role = 'kasir';
        $this->is_active = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function editUser(string $id): void
    {
        $user = User::findOrFail($id);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->username = $user->username ?? '';
        $this->email = $user->email ?? '';
        $this->role = $user->role->value;
        $this->password = '';
        $this->pin = '';
        $this->is_active = (bool) $user->is_active;

        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'branch_id' => 'BR01',
            'name' => $this->name,
            'username' => $this->username,
            'email' => !empty($this->email) ? $this->email : ($this->username . '@apotek.local'),
            'role' => UserRole::from($this->role),
            'is_active' => $this->is_active,
        ];

        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        if (!empty($this->pin)) {
            $data['pin_hash'] = Hash::make($this->pin);
        }

        if ($this->userId) {
            $user = User::findOrFail($this->userId);
            $user->update($data);
            session()->flash('success', "Pegawai {$this->name} berhasil diperbarui.");
        } else {
            if (empty($this->password)) {
                $data['password'] = Hash::make('password123');
            }
            User::create($data);
            session()->flash('success', "Pegawai baru {$this->name} berhasil ditambahkan.");
        }

        $this->showModal = false;
    }

    public function toggleStatus(string $id): void
    {
        $user = User::findOrFail($id);
        $user->is_active = !$user->is_active;
        $user->save();

        session()->flash('success', "Status pegawai {$user->name} berhasil diubah.");
    }

    public function render()
    {
        $query = User::query();

        if (trim($this->search) !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('username', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        $users = $query->orderBy('name', 'asc')->paginate(10);

        return view('livewire.users.user-manager', [
            'users' => $users,
        ]);
    }
}
