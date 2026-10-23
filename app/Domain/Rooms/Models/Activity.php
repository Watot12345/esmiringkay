<?php

namespace App\Domain\Rooms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use HasFactory;

    public const KIND_DOODLE = 'doodle';

    public const KIND_TRIVIA = 'trivia';

    public const KIND_BREATHING = 'breathing';

    public const KIND_ICEBREAKER = 'icebreaker';

    protected $fillable = [
        'room_id',
        'kind',
        'state',
    ];

    protected $casts = [
        'state' => 'array',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
