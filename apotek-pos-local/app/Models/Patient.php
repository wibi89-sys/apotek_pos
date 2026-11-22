<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'nik',
        'phone',
        'birth_date',
        'gender',
        'address',
        'allergies',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];
}
