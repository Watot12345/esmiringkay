<?php

namespace App\Domain\Plaza\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlazaEvent extends Model
{
    use HasFactory;

    protected $table = 'plaza_events';

    protected $fillable = [
        'title',
        'description',
        'scheduled_at',
        'vibe',
        'host_name',
        'attendees_count',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'attendees_count' => 'integer',
        ];
    }
}
