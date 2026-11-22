<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\User;
use Livewire\Component;

class ShiftManager extends Component
{
    public ?Shift $activeShift = null;
    public string $openingCash = '100000';
    public string $actualCashClosing = '';
    public ?string $errorMessage = null;
    public ?string $successMessage = null;

    public function mount(): void
    {
        $terminalId = (string) config('license.terminal_id', 'POS01');
        $this->activeShift = Shift::currentOpenShift($terminalId);
    }

    public function openShift(): void
    {
        $this->validate([
            'openingCash' => ['required', 'numeric', 'min:0'],
        ]);

        $terminalId = (string) config('license.terminal_id', 'POS01');
        $branchId = (string) config('license.branch_id', 'BR01');

        $userId = auth()->id();
        if (! $userId) {
            $defaultUser = User::first();
            $userId = $defaultUser ? $defaultUser->id : null;
        }

        $this->activeShift = Shift::create([
            'branch_id' => $branchId,
            'terminal_id' => $terminalId,
            'user_id' => $userId,
            'opened_at' => now(),
            'opening_cash' => (string) $this->openingCash,
            'status' => ShiftStatus::OPEN,
            'sync_status' => 'pending',
        ]);

        session()->flash('success', 'Shift kasir berhasil dibuka dengan modal awal Rp ' . number_format((float) $this->openingCash, 0, ',', '.'));
        $this->redirectRoute('pos.index');
    }

    public function closeShift(): void
    {
        $this->validate([
            'actualCashClosing' => ['required', 'numeric', 'min:0'],
        ]);

        if (! $this->activeShift) {
            $this->errorMessage = 'Tidak ada shift aktif yang dapat ditutup.';
            return;
        }

        $this->activeShift->closeShift((string) $this->actualCashClosing);
        $expected = $this->activeShift->expected_cash;
        $diff = $this->activeShift->cash_difference;

        $diffMsg = bccomp((string) $diff, '0.00', 2) === 0
            ? 'Uang fisik COCOK pas dengan sistem.'
            : (bccomp((string) $diff, '0.00', 2) === 1
                ? 'Selisih LEBIH: Rp ' . number_format((float) $diff, 0, ',', '.')
                : 'Selisih KURANG: Rp ' . number_format((float) abs((float) $diff), 0, ',', '.'));

        session()->flash('success', "Shift kasir resmi ditutup! Expected: Rp " . number_format((float) $expected, 0, ',', '.') . " | Fisik: Rp " . number_format((float) $this->actualCashClosing, 0, ',', '.') . " ({$diffMsg})");

        $this->activeShift = null;
        $this->actualCashClosing = '';
    }

    public function render()
    {
        return view('livewire.pos.shift-manager')
            ->layout('components.layouts.app', ['title' => 'Manajemen Shift Kasir']);
    }
}
