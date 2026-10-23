<?php

namespace App\Domain\Plaza\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlazaMessage extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'plaza_messages';

    protected $fillable = [
        'id',
        'plaza_room_id',
        'user_id',
        'body',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(PlazaRoom::class, 'plaza_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
