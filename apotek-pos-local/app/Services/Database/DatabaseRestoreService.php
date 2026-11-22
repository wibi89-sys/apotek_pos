<?php

declare(strict_types=1);

namespace App\Services\Database;

use App\Models\DbBackupLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class DatabaseRestoreService
{
    protected string $mysqlBinary;

    public function __construct(
        protected DatabaseBackupService $backupService
    ) {
        $this->mysqlBinary = env('MYSQL_PATH', 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe');
    }

    /**
     * Restore database from a given backup .zip file.
     * Enforces MANDATORY pre-restore emergency snapshot before any restore action!
     */
    public function restoreFromZip(string $zipPath): array
    {
        if (! File::exists($zipPath)) {
            throw new RuntimeException("File backup tidak ditemukan: {$zipPath}");
        }

        // 1. Mandatory Pre-Restore Emergency Snapshot (HARD CONSTRAINT #11)
        $preRestoreSnapshot = $this->backupService->createBackup('pre_restore');

        if (! $preRestoreSnapshot || $preRestoreSnapshot->status !== 'completed') {
            throw new RuntimeException('Gagal membuat snapshot darurat pra-restore. Proses restore dibatalkan demi keamanan data!');
        }

        // 2. Extract .sql from .zip
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Gagal membuka file arsip ZIP: {$zipPath}");
        }

        $tempExtractDir = storage_path('app/backups/temp_restore_' . uniqid());
        File::ensureDirectoryExists($tempExtractDir);
        $zip->extractTo($tempExtractDir);
        $zip->close();

        // Locate extracted .sql file
        $sqlFiles = File::files($tempExtractDir);
        $sqlFile = null;
        foreach ($sqlFiles as $file) {
            if ($file->getExtension() === 'sql') {
                $sqlFile = $file->getRealPath();
                break;
            }
        }

        if (! $sqlFile) {
            File::deleteDirectory($tempExtractDir);
            throw new RuntimeException("File dump .sql tidak ditemukan di dalam arsip ZIP {$zipPath}");
        }

        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();

            if ($driver === 'mysql') {
                $dbHost = (string) config('database.connections.mysql.host', '127.0.0.1');
                $dbPort = (string) config('database.connections.mysql.port', '3306');
                $dbUser = (string) config('database.connections.mysql.username', 'root');
                $dbPass = (string) config('database.connections.mysql.password', '');
                $dbName = (string) config('database.connections.mysql.database', 'apotek_local');

                $cmd = [
                    $this->mysqlBinary,
                    "--host={$dbHost}",
                    "--port={$dbPort}",
                    "--user={$dbUser}",
                ];

                if ($dbPass !== '') {
                    $cmd[] = "--password={$dbPass}";
                }

                $cmd[] = $dbName;

                // Execute restore via Symfony Process array (input passed as raw string content)
                $process = new Process($cmd);
                $process->setInput(File::get($sqlFile));
                $process->setTimeout(600);
                $process->run();

                if (! $process->isSuccessful()) {
                    throw new RuntimeException('MySQL restore failed: ' . $process->getErrorOutput());
                }
            } else {
                // SQLite restore execution
                $sqlCommands = File::get($sqlFile);
                DB::unprepared($sqlCommands);
            }

            // Clean up temporary extracted directory
            File::deleteDirectory($tempExtractDir);

            // Re-record pre-restore snapshot log into restored database if not present
            if (! DbBackupLog::where('filename', $preRestoreSnapshot->filename)->exists()) {
                DbBackupLog::create([
                    'filename' => $preRestoreSnapshot->filename,
                    'file_size_bytes' => $preRestoreSnapshot->file_size_bytes,
                    'storage_path' => $preRestoreSnapshot->storage_path,
                    'trigger_type' => 'pre_restore',
                    'status' => 'completed',
                ]);
            }

            // Clear cache and views
            Artisan::call('optimize:clear');

            return [
                'status' => 'success',
                'pre_restore_backup' => $preRestoreSnapshot->filename,
                'restored_file' => basename($zipPath),
            ];
        } catch (\Throwable $e) {
            File::deleteDirectory($tempExtractDir);
            throw $e;
        }
    }
}
