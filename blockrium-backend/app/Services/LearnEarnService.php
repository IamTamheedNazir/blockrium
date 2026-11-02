<?php

namespace App\Services;

use App\Models\ResUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LearnEarnService
{
    private $gamification;
    private $walletRewards;

    public function __construct(GamificationService $gamification, WalletRewardService $walletRewards)
    {
        $this->gamification = $gamification;
        $this->walletRewards = $walletRewards;
    }

    public function modulesForUser(ResUser $user): array
    {
        if (!Schema::hasTable('learn_modules')) {
            return ['modules' => []];
        }

        $progress = Schema::hasTable('user_learn_progress')
            ? DB::table('user_learn_progress')->where('user_id', (int) $user->id)->get()->keyBy('module_id')
            : collect();

        $modules = DB::table('learn_modules')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function ($module) use ($progress) {
                $row = $progress->get($module->id);
                $completed = $row && $row->status === 'completed';

                return [
                    'id' => (int) $module->id,
                    'key' => $module->key,
                    'title' => $module->title,
                    'description' => $module->description,
                    'youtube_url' => $module->youtube_url,
                    'youtube_video_id' => $module->youtube_video_id,
                    'estimated_minutes' => $module->estimated_minutes !== null ? (int) $module->estimated_minutes : null,
                    'reward_xp' => (int) $module->reward_xp,
                    'reward_brc' => $module->reward_brc !== null ? (float) $module->reward_brc : null,
                    'status' => $completed ? 'completed' : 'not_started',
                    'completed' => (bool) $completed,
                    'locked' => false,
                    'sort_order' => (int) $module->sort_order,
                ];
            })
            ->all();

        return ['modules' => $modules];
    }

    public function moduleDetail(ResUser $user, int $moduleId): ?array
    {
        if (!Schema::hasTable('learn_modules')) {
            return null;
        }

        $module = DB::table('learn_modules')->where('id', $moduleId)->where('is_active', 1)->first();
        if (!$module) {
            return null;
        }

        $progress = Schema::hasTable('user_learn_progress')
            ? DB::table('user_learn_progress')->where('user_id', (int) $user->id)->where('module_id', $moduleId)->first()
            : null;

        return [
            'module' => $this->modulePayload($module, true),
            'questions' => $this->safeQuestions($moduleId),
            'progress' => [
                'status' => $progress->status ?? 'not_started',
                'completed' => $progress && $progress->status === 'completed',
                'score' => $progress && $progress->score !== null ? (int) $progress->score : null,
                'completed_at' => $progress->completed_at ?? null,
                'reward_claimed_at' => $progress->reward_claimed_at ?? null,
            ],
        ];
    }

    public function completeModule(ResUser $user, int $moduleId, array $answers): array
    {
        if (!Schema::hasTable('learn_modules') || !Schema::hasTable('learn_questions') || !Schema::hasTable('user_learn_progress')) {
            return [
                'success' => false,
                'status' => 503,
                'message' => 'Learn & Earn is temporarily unavailable.',
                'data' => [],
            ];
        }

        $module = DB::table('learn_modules')->where('id', $moduleId)->where('is_active', 1)->first();
        if (!$module) {
            return [
                'success' => false,
                'status' => 404,
                'message' => 'Lesson not found.',
                'data' => [],
            ];
        }

        $existing = DB::table('user_learn_progress')
            ->where('user_id', (int) $user->id)
            ->where('module_id', $moduleId)
            ->first();

        if ($existing && $existing->status === 'completed') {
            return [
                'success' => true,
                'status' => 200,
                'message' => 'Lesson already completed.',
                'data' => [
                    'completed' => true,
                    'score' => (int) $existing->score,
                    'reward_xp' => 0,
                    'reward_brc' => null,
                    'gamification' => $this->gamification->getSummary($user),
                ],
            ];
        }

        $questions = DB::table('learn_questions')
            ->where('module_id', $moduleId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($questions->isEmpty()) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'This lesson does not have quiz questions yet.',
                'data' => ['completed' => false],
            ];
        }

        $answerMap = $this->normalizeAnswers($answers);
        $correct = 0;
        $summary = [];

        foreach ($questions as $question) {
            $selected = $answerMap[(int) $question->id] ?? null;
            $isCorrect = $selected !== null && strtoupper($selected) === strtoupper((string) $question->correct_option);
            if ($isCorrect) {
                $correct++;
            }
            $summary[] = [
                'question_id' => (int) $question->id,
                'selected_option' => $selected,
                'correct' => $isCorrect,
            ];
        }

        $score = (int) round(($correct / max(1, $questions->count())) * 100);
        $passingScore = (int) $module->passing_score;

        if ($score < $passingScore) {
            DB::table('user_learn_progress')->updateOrInsert(
                ['user_id' => (int) $user->id, 'module_id' => $moduleId],
                [
                    'status' => 'not_started',
                    'score' => $score,
                    'answers' => json_encode($summary),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            return [
                'success' => false,
                'status' => 422,
                'message' => 'Some answers are incorrect. Please review the lesson and try again.',
                'data' => [
                    'completed' => false,
                    'score' => $score,
                    'passing_score' => $passingScore,
                ],
            ];
        }

        $rewardXp = (int) $module->reward_xp;
        $rewardBrc = $module->reward_brc !== null ? (float) $module->reward_brc : null;

        $result = DB::transaction(function () use ($user, $module, $moduleId, $score, $summary, $rewardXp, $rewardBrc) {
            $now = now();
            $lockedProgress = DB::table('user_learn_progress')
                ->where('user_id', (int) $user->id)
                ->where('module_id', $moduleId)
                ->lockForUpdate()
                ->first();

            if ($lockedProgress && $lockedProgress->status === 'completed') {
                return [
                    'already_completed' => true,
                    'gamification' => $this->gamification->getSummary($user),
                    'wallet' => null,
                ];
            }

            DB::table('user_learn_progress')->updateOrInsert(
                ['user_id' => (int) $user->id, 'module_id' => $moduleId],
                [
                    'status' => 'completed',
                    'score' => $score,
                    'answers' => json_encode($summary),
                    'completed_at' => $now,
                    'reward_claimed_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $gamification = $this->gamification->awardXp(
                $user,
                'lesson_completed',
                $rewardXp,
                'learn_module',
                $moduleId,
                ['module_key' => $module->key]
            );

            $wallet = null;
            if ($rewardBrc !== null && $rewardBrc > 0) {
                $wallet = $this->walletRewards->credit((int) $user->id, $rewardBrc);
            }

            return [
                'gamification' => $gamification['summary'] ?? $this->gamification->getSummary($user),
                'wallet' => $wallet,
            ];
        }, 3);

        if (!empty($result['already_completed'])) {
            return [
                'success' => true,
                'status' => 200,
                'message' => 'Lesson already completed.',
                'data' => [
                    'completed' => true,
                    'score' => $score,
                    'reward_xp' => 0,
                    'reward_brc' => null,
                    'gamification' => $result['gamification'],
                ],
            ];
        }

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Lesson completed. XP awarded.',
            'data' => [
                'completed' => true,
                'score' => $score,
                'reward_xp' => $rewardXp,
                'reward_brc' => $rewardBrc,
                'gamification' => $result['gamification'],
            ],
        ];
    }

    public function extractYoutubeVideoId(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $path = isset($parts['path']) ? trim((string) $parts['path'], '/') : '';

        if ($host === 'youtu.be') {
            $segments = explode('/', $path);
            return $this->cleanVideoId($segments[0] ?? null);
        }

        if ($host === 'youtube.com' || $host === 'www.youtube.com' || $host === 'm.youtube.com') {
            if ($path === 'watch') {
                parse_str($parts['query'] ?? '', $query);
                return $this->cleanVideoId($query['v'] ?? null);
            }
            if (preg_match('#^(shorts|embed)/([^/]+)#', $path, $matches)) {
                return $this->cleanVideoId($matches[2]);
            }
        }

        return null;
    }

    public function validateYoutubeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $videoId = $this->extractYoutubeVideoId($url);
        if (!$videoId) {
            throw ValidationException::withMessages([
                'youtube_url' => 'Use a valid YouTube watch, youtu.be, shorts, or embed URL.',
            ]);
        }

        return $videoId;
    }

    private function modulePayload($module, bool $includeContent = false): array
    {
        $payload = [
            'id' => (int) $module->id,
            'key' => $module->key,
            'title' => $module->title,
            'description' => $module->description,
            'youtube_url' => $module->youtube_url,
            'youtube_video_id' => $module->youtube_video_id,
            'estimated_minutes' => $module->estimated_minutes !== null ? (int) $module->estimated_minutes : null,
            'reward_xp' => (int) $module->reward_xp,
            'reward_brc' => $module->reward_brc !== null ? (float) $module->reward_brc : null,
            'passing_score' => (int) $module->passing_score,
            'sort_order' => (int) $module->sort_order,
        ];

        if ($includeContent) {
            $payload['content'] = $module->content;
        }

        return $payload;
    }

    private function safeQuestions(int $moduleId): array
    {
        if (!Schema::hasTable('learn_questions')) {
            return [];
        }

        return DB::table('learn_questions')
            ->where('module_id', $moduleId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function ($question) {
                return [
                    'id' => (int) $question->id,
                    'question' => $question->question,
                    'options' => json_decode((string) $question->options, true) ?: [],
                    'sort_order' => (int) $question->sort_order,
                ];
            })
            ->all();
    }

    private function normalizeAnswers(array $answers): array
    {
        $map = [];
        foreach ($answers as $answer) {
            if (!is_array($answer)) {
                continue;
            }
            $questionId = isset($answer['question_id']) ? (int) $answer['question_id'] : 0;
            $selected = strtoupper(trim((string) ($answer['selected_option'] ?? '')));
            if ($questionId > 0 && in_array($selected, ['A', 'B', 'C', 'D'])) {
                $map[$questionId] = $selected;
            }
        }

        return $map;
    }

    private function cleanVideoId($value): ?string
    {
        $value = trim((string) $value);
        if (preg_match('/^[A-Za-z0-9_-]{6,80}$/', $value)) {
            return $value;
        }

        return null;
    }
}
