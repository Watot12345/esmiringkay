<?php

namespace App\Domain\Plaza\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPromptAnswer extends Model
{
    use HasFactory;

    protected $table = 'daily_prompt_answers';

    protected $fillable = [
        'daily_prompt_id',
        'user_id',
        'answer',
        'mood_score',
    ];

    protected function casts(): array
    {
        return [
            'mood_score' => 'integer',
        ];
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(DailyPrompt::class, 'daily_prompt_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
