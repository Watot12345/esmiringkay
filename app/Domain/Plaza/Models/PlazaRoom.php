<?php

namespace App\Domain\Plaza\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlazaRoom extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'plaza_rooms';

    protected $fillable = [
        'id',
        'topic',
        'vibe',
        'description',
        'host_id',
        'capacity',
        'current_participants',
        'is_active',
        'starts_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'current_participants' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(PlazaRoomMember::class, 'plaza_room_id');
    }

    public function activeMembers(): HasMany
    {
        return $this->hasMany(PlazaRoomMember::class, 'plaza_room_id')->whereNull('left_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PlazaMessage::class, 'plaza_room_id');
    }

    public function isFull(): bool
    {
        return $this->activeMembers()->count() >= $this->capacity;
    }

    public function hasUser(int $userId): bool
    {
        return $this->activeMembers()->where('user_id', $userId)->exists();
    }
}
