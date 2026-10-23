<?php

namespace App\Domain\Rooms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'type',
        'vibe',
        'started_at',
        'ended_at',
        'end_reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'room_participants')
            ->withPivot(['joined_at', 'left_at', 'skipped'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
