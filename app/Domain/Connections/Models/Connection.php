<?php

namespace App\Domain\Connections\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Connection extends Model
{
    use HasFactory;

    // Types per 01-context.md Section 5: friend, rival, spark, confidant
    public const TYPE_FRIEND = 'friend';

    public const TYPE_RIVAL = 'rival';

    public const TYPE_SPARK = 'spark';

    public const TYPE_CONFIDANT = 'confidant';

    protected $fillable = [
        'user_a',
        'user_b',
        'type',
        'status',
    ];

    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a');
    }

    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b');
    }

    public function directMessages(): HasMany
    {
        return $this->hasMany(DirectMessage::class);
    }
}
