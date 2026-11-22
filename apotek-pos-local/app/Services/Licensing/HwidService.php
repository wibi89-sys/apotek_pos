<?php

declare(strict_types=1);

namespace App\Services\Licensing;

use Symfony\Component\Process\Process;

class HwidService
{
    private const SALT = 'APOTEK_POS_HYBRID_HARDWARE_SALT_v1';

    /**
     * Get the machine Hardware ID (SHA-256 hash).
     */
    public function getHwid(): string
    {
        // Support testing / mock HWID
        $mock = config('license.mock_hwid');
        if (! empty($mock)) {
            return (string) $mock;
        }

        // On non-Windows (e.g. CI environments), fallback gracefully to machine-id / host hash
        if (PHP_OS_FAMILY !== 'Windows') {
            return hash('sha256', gethostname().self::SALT);
        }

        try {
            $process = new Process([
                'powershell.exe',
                '-NoProfile',
                '-ExecutionPolicy',
                'Bypass',
                '-Command',
                '(Get-CimInstance Win32_ComputerSystemProduct).UUID; (Get-CimInstance Win32_DiskDrive | Select-Object -First 1).SerialNumber',
            ]);

            $process->setTimeout(3.0);
            $process->run();

            if ($process->isSuccessful()) {
                $output = preg_replace('/\r\n|\r|\n/', "\n", trim($process->getOutput()));
                if (! empty($output)) {
                    return hash('sha256', $output.self::SALT);
                }
            }
        } catch (\Throwable) {
            // Fallback if PowerShell execution fails
        }

        return hash('sha256', gethostname().self::SALT);
    }
}
