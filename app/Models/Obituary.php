<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Obituary extends Model
{
    use HasFactory;

    protected $table = 'obituaries';

    protected $fillable = [
        'date',
        'cemetery',
        'park',
        'deceased_name',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}


