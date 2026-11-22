<?php

declare(strict_types=1);

namespace App\Services\Database;

use App\Models\DbBackupLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class DatabaseBackupService
{
    protected string $backupDir;
    protected string $dumpBinary;

    public function __construct()
    {
        $this->backupDir = storage_path('app/backups');
        $this->dumpBinary = env('MYSQL_DUMP_PATH', 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe');

        File::ensureDirectoryExists($this->backupDir);
    }

    /**
     * Create a compressed database backup (.zip) and record it in db_backup_logs.
     */
    public function createBackup(string $triggerType = 'manual'): DbBackupLog
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $branchId = (string) config('license.branch_id', 'BR01');
        $baseName = "backup_{$branchId}_{$triggerType}_{$timestamp}";
        $sqlPath = "{$this->backupDir}/{$baseName}.sql";
        $zipFilename = "{$baseName}.zip";
        $zipPath = "{$this->backupDir}/{$zipFilename}";

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
                    $this->dumpBinary,
                    "--host={$dbHost}",
                    "--port={$dbPort}",
                    "--user={$dbUser}",
                ];

                if ($dbPass !== '') {
                    $cmd[] = "--password={$dbPass}";
                }

                $cmd[] = '--single-transaction';
                $cmd[] = '--quick';
                $cmd[] = '--routines';
                $cmd[] = '--triggers';
                $cmd[] = $dbName;

                // Execute via Symfony Process array (Strictly no shell_exec or raw command strings)
                $process = new Process($cmd);
                $process->setTimeout(300);
                $process->run();

                if (! $process->isSuccessful()) {
                    throw new RuntimeException('mysqldump failed: ' . $process->getErrorOutput());
                }

                File::put($sqlPath, $process->getOutput());
            } else {
                // SQLite / Testing fallback dump
                $sqlContent = "-- APOTEK POS SQLITE DUMP {$timestamp}\nPRAGMA foreign_keys = OFF;\n";
                $tables = $connection->select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                foreach ($tables as $table) {
                    $sqlContent .= "DROP TABLE IF EXISTS \"{$table->name}\";\n";
                    $sqlContent .= $table->sql . ";\n";
                    $rows = $connection->select("SELECT * FROM \"{$table->name}\"");
                    foreach ($rows as $row) {
                        $values = array_map(fn($v) => is_null($v) ? 'NULL' : "'" . addslashes((string)$v) . "'", (array)$row);
                        $sqlContent .= "INSERT INTO \"{$table->name}\" VALUES (" . implode(',', $values) . ");\n";
                    }
                }
                $sqlContent .= "PRAGMA foreign_keys = ON;\n";
                File::put($sqlPath, $sqlContent);
            }

            // Compress to ZIP using PHP ZipArchive
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Cannot create zip archive at {$zipPath}");
            }

            $zip->addFile($sqlPath, basename($sqlPath));
            $zip->close();

            // Immediately remove uncompressed temporary SQL file
            if (File::exists($sqlPath)) {
                File::delete($sqlPath);
            }

            $fileSize = File::size($zipPath);

            // Record log in db_backup_logs
            $log = DbBackupLog::create([
                'filename' => $zipFilename,
                'file_size_bytes' => $fileSize,
                'storage_path' => $zipPath,
                'trigger_type' => $triggerType,
                'status' => 'completed',
            ]);

            // Enforce 30-day retention cleanup
            $this->cleanupOldBackups(30);

            return $log;
        } catch (\Throwable $e) {
            // Clean up temporary sql file on error
            if (File::exists($sqlPath)) {
                File::delete($sqlPath);
            }

            DbBackupLog::create([
                'filename' => $zipFilename,
                'file_size_bytes' => 0,
                'storage_path' => $zipPath,
                'trigger_type' => $triggerType,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete backups older than specified retention days.
     */
    public function cleanupOldBackups(int $retentionDays = 30): int
    {
        $cutoff = Carbon::now()->subDays($retentionDays);
        $deletedCount = 0;

        // Clean database records
        $oldLogs = DbBackupLog::where('created_at', '<', $cutoff)->get();
        foreach ($oldLogs as $log) {
            if (File::exists($log->storage_path)) {
                File::delete($log->storage_path);
            }
            $log->delete();
            $deletedCount++;
        }

        // Also clean orphaned physical files older than retention days
        $files = File::files($this->backupDir);
        foreach ($files as $file) {
            if ($file->getExtension() === 'zip') {
                $lastModified = Carbon::createFromTimestamp($file->getMTime());
                if ($lastModified->lt($cutoff)) {
                    File::delete($file->getRealPath());
                    $deletedCount++;
                }
            }
        }

        return $deletedCount;
    }

    public function getBackupDirectory(): string
    {
        return $this->backupDir;
    }
}
