<?php

namespace App\Domain\Accounts\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'avatar',
        'bio',
        'languages',
        'interests',
    ];

    protected $casts = [
        'languages' => 'array',
        'interests' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
