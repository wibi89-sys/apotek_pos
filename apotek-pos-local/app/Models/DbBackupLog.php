<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbBackupLog extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'filename',
        'file_size_bytes',
        'storage_path',
        'trigger_type',
        'status',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
    ];

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size_bytes;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    public function getTriggerLabelAttribute(): string
    {
        return match ($this->trigger_type) {
            'scheduled' => 'Jadwal Otomatis (23:00)',
            'shift_close' => 'Tutup Shift Kasir',
            'manual' => 'Manual oleh User',
            'pre_restore' => 'Snapshot Darurat Pra-Restore',
            default => $this->trigger_type,
        };
    }
}
