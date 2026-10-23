<?php

namespace App\Models;

use App\Domain\Accounts\Models\Profile;
use App\Domain\Connections\Models\Connection;
use App\Domain\Rooms\Models\Room;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'display_name',
        'email',
        'phone',
        'password',
        'birthdate_confirmed',
        'verified_at',
        'trust_score',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'birthdate_confirmed' => 'boolean',
            'trust_score' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'room_participants')
            ->withPivot(['joined_at', 'left_at', 'skipped'])
            ->withTimestamps();
    }

    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class, 'user_a');
    }
}
