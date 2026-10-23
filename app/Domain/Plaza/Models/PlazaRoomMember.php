<?php

namespace App\Domain\Plaza\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlazaRoomMember extends Model
{
    use HasFactory;

    protected $table = 'plaza_room_members';

    protected $fillable = [
        'plaza_room_id',
        'user_id',
        'role',
        'is_muted',
        'joined_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'is_muted' => 'boolean',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(PlazaRoom::class, 'plaza_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
