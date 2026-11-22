<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Models\User;
use App\Services\Hardware\FingerprintService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class FingerprintEnrollment extends Component
{
    public ?string $selectedUserId = null;
    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function enroll(string $userId): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        $user = User::find($userId);
        if (! $user) {
            $this->errorMessage = 'Pengguna tidak ditemukan.';
            return;
        }

        try {
            $service = app(FingerprintService::class);
            $result = $service->enrollUser($user);

            $this->successMessage = $result['message'];
        } catch (\Throwable $e) {
            $this->errorMessage = 'Pendaftaran sidik jari gagal: ' . $e->getMessage();
        }
    }

    public function clearFingerprint(string $userId): void
    {
        $user = User::find($userId);
        if ($user) {
            $user->fingerprint_template = null;
            $user->save();
            $this->successMessage = "Data sidik jari pengguna {$user->name} berhasil dihapus.";
        }
    }

    public function render()
    {
        $users = User::orderBy('name')->get();
        $service = app(FingerprintService::class);
        $hwStatus = $service->getHardwareStatus();

        return view('livewire.users.fingerprint-enrollment', [
            'users' => $users,
            'hwStatus' => $hwStatus,
        ]);
    }
}
