<?php

namespace App\Domain\Moderation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sanction extends Model
{
    use HasFactory;

    // Types per 03-system-requirements.md FR-43
    public const TYPE_WARN = 'warn';

    public const TYPE_MUTE = 'mute';

    public const TYPE_TEMP_BAN = 'temp_ban';

    public const TYPE_PERM_BAN = 'perm_ban';

    public const TYPE_DEVICE_BAN = 'device_ban';

    protected $fillable = [
        'user_id',
        'type',
        'reason',
        'expires_at',
        'appeal_status',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
