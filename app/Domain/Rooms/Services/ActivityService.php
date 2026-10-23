<?php

namespace App\Domain\Rooms\Services;

use App\Domain\Rooms\Events\ActivityUpdated;
use App\Domain\Rooms\Models\Activity;
use App\Domain\Rooms\Models\Room;

class ActivityService
{
    protected array $triviaBank = [
        [
            'id' => 1,
            'question' => 'Which planet in our solar system has the most moons?',
            'options' => ['Jupiter', 'Saturn', 'Neptune', 'Mars'],
            'correct' => 1, // Saturn (146 moons)
        ],
        [
            'id' => 2,
            'question' => 'What is the national animal of the Philippines?',
            'options' => ['Tarsier', 'Carabao', 'Philippine Eagle', 'Tamaraw'],
            'correct' => 1, // Carabao
        ],
        [
            'id' => 3,
            'question' => 'How many hearts does an octopus have?',
            'options' => ['One', 'Two', 'Three', 'Four'],
            'correct' => 2, // Three
        ],
        [
            'id' => 4,
            'question' => 'Which color calm room walls are proven to lower heart rate?',
            'options' => ['Soft Blue', 'Bright Red', 'Yellow', 'Black'],
            'correct' => 0, // Soft Blue
        ],
        [
            'id' => 5,
            'question' => 'Which fruit produces the calming compound linalool often found in aromatherapy?',
            'options' => ['Lemon', 'Lavender Citrus', 'Bergamot', 'Papaya'],
            'correct' => 2, // Bergamot
        ],
    ];

    /**
     * Get or create active activity state for a live room.
     */
    public function getActivityState(string $roomId, string $kind): Activity
    {
        $room = Room::findOrFail($roomId);

        return Activity::firstOrCreate(
            ['room_id' => $room->id, 'kind' => $kind],
            ['state' => $this->getDefaultState($kind)]
        );
    }

    /**
     * Update activity state and broadcast to both room peers.
     */
    public function updateActivity(string $roomId, string $kind, string $action, array $payload): Activity
    {
        $activity = $this->getActivityState($roomId, $kind);
        $state = $activity->state ?? [];

        switch ($kind) {
            case Activity::KIND_DOODLE:
                if ($action === 'clear') {
                    $state['strokes'] = [];
                } elseif ($action === 'draw' && isset($payload['stroke'])) {
                    $state['strokes'] = $state['strokes'] ?? [];
                    $state['strokes'][] = $payload['stroke'];
                    // Keep last 150 strokes to avoid bloated payloads
                    if (count($state['strokes']) > 150) {
                        array_shift($state['strokes']);
                    }
                }
                break;

            case Activity::KIND_TRIVIA:
                if ($action === 'answer') {
                    $sender = $payload['sender'] ?? 'peer';
                    $isCorrect = (bool) ($payload['is_correct'] ?? false);
                    $state['scores'] = $state['scores'] ?? ['you' => 0, 'stranger' => 0];
                    if ($isCorrect) {
                        $state['scores'][$sender] = ($state['scores'][$sender] ?? 0) + 1;
                    }
                } elseif ($action === 'next_question') {
                    $currentIndex = ($state['question_index'] ?? 0) + 1;
                    $state['question_index'] = $currentIndex % count($this->triviaBank);
                    $state['current_question'] = $this->triviaBank[$state['question_index']];
                }
                break;

            case Activity::KIND_BREATHING:
                $state['phase'] = $payload['phase'] ?? 'inhale';
                break;
        }

        $activity->update(['state' => $state]);

        broadcast(new ActivityUpdated($roomId, $kind, $action, $payload))->toOthers();

        return $activity;
    }

    protected function getDefaultState(string $kind): array
    {
        return match ($kind) {
            Activity::KIND_DOODLE => ['strokes' => []],
            Activity::KIND_TRIVIA => [
                'question_index' => 0,
                'current_question' => $this->triviaBank[0],
                'scores' => ['you' => 0, 'stranger' => 0],
            ],
            Activity::KIND_BREATHING => ['phase' => 'inhale'],
            Activity::KIND_ICEBREAKER => ['card_index' => 0],
            default => [],
        };
    }
}
