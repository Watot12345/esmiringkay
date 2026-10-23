<?php

namespace App\Domain\Plaza\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyPrompt extends Model
{
    use HasFactory;

    protected $table = 'daily_prompts';

    protected $fillable = [
        'prompt',
        'date',
        'responses_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'responses_count' => 'integer',
        ];
    }

    public function answers(): HasMany
    {
        return $this->hasMany(DailyPromptAnswer::class, 'daily_prompt_id');
    }
}
