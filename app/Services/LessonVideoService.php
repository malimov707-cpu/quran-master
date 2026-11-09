<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\User;
use App\Models\VideoProgress;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class LessonVideoService
{
    public function authorize(User $user, Lesson $lesson): void
    {
        abort_unless($user->is_active && $user->hasPermission('lessons.view'), 403);
        if ($user->hasPermission('lessons.update')) {
            return;
        }
        abort_unless($lesson->status === 'published', 404);
        $hasGroup = $lesson->course->groups()->whereIn('status', ['active', 'completed'])->where(function (Builder $query) use ($user): void {
            $query->whereHas('students', fn (Builder $students): Builder => $students->where('users.id', $user->id))
                ->orWhere('teacher_id', $user->id)->orWhere('mentor_id', $user->id);
        })->exists();
        abort_unless($hasGroup, 404);
    }

    public function authorizeMaterial(User $user, LessonMaterial $material): void
    {
        abort_unless($material->type === 'video' && in_array($material->payload['provider'] ?? null, ['upload', 'youtube'], true), 404);
        $this->authorize($user, $material->lesson);
    }

    /** @return array<string, mixed> */
    public function videoData(User $user, LessonMaterial $material, ?VideoProgress $progress = null): array
    {
        $payload = $material->payload;
        $uploaded = $payload['provider'] === 'upload';
        $params = ['material' => $material->id, 'user' => $user->id, 'version' => $payload['version']];
        $url = $uploaded ? rtrim((string) config('app.url'), '/').URL::temporarySignedRoute('lesson-video.stream', now()->addHours(6), $params, absolute: false) : null;
        $downloadUrl = $uploaded && ($payload['offline_allowed'] ?? false)
            ? rtrim((string) config('app.url'), '/').URL::temporarySignedRoute('lesson-video.stream', now()->addDays(7), $params + ['download' => 1], absolute: false) : null;
        $duration = (int) $payload['duration_seconds'];

        return ['can_track' => $user->hasRole('STUDENT'), 'id' => $material->id, 'lesson_id' => $material->lesson_id, 'title' => $material->title,
            'provider' => $payload['provider'], 'youtube_id' => $payload['youtube_id'] ?? null, 'duration_seconds' => $duration,
            'size_bytes' => $payload['size_bytes'] ?? null, 'version' => $payload['version'], 'url' => $url, 'download_url' => $downloadUrl,
            'offline_allowed' => $downloadUrl !== null, 'mime_type' => $payload['mime_type'] ?? null,
            'progress' => ['position_seconds' => $progress?->position_seconds ?? 0, 'ranges' => $progress?->ranges ?? [],
                'watched_seconds' => $progress?->watched_seconds ?? 0, 'completed' => $progress?->completed ?? false,
                'observed_at' => $progress?->observed_at?->toISOString(),
                'percent' => $progress ? round(min(100, $progress->watched_seconds / $duration * 100), 1) : 0]];
    }

    /** @param array{position_seconds: int, ranges: list<array{0: int, 1: int}>, observed_at: string} $data */
    public function saveProgress(User $user, LessonMaterial $material, array $data): VideoProgress
    {
        $this->authorizeMaterial($user, $material);
        abort_unless($user->hasRole('STUDENT'), 403);
        $duration = (int) $material->payload['duration_seconds'];
        $observed = CarbonImmutable::parse($data['observed_at'])->min(CarbonImmutable::now());

        return DB::transaction(function () use ($user, $material, $data, $duration, $observed): VideoProgress {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $progress = VideoProgress::firstOrNew(['user_id' => $user->id, 'lesson_material_id' => $material->id]);
            $ranges = $this->mergeRanges(array_merge($progress->ranges ?? [], $data['ranges']), $duration);
            $watched = array_sum(array_map(fn (array $range): int => $range[1] - $range[0], $ranges));
            if (! $progress->observed_at || $observed->greaterThanOrEqualTo($progress->observed_at)) {
                $progress->position_seconds = min($duration, (int) $data['position_seconds']);
                $progress->observed_at = $observed;
            }
            $progress->fill(['ranges' => $ranges, 'watched_seconds' => $watched, 'completed' => $watched >= $duration * 0.9])->save();

            return $progress;
        }, attempts: 3);
    }

    /** @param list<array{0: int, 1: int}> $ranges
     * @return list<array{0: int, 1: int}>
     */
    public function mergeRanges(array $ranges, int $duration): array
    {
        usort($ranges, fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($ranges as [$from, $to]) {
            $from = max(0, min($duration, (int) $from));
            $to = max($from, min($duration, (int) $to));
            if ($to === $from) {
                continue;
            }
            $last = count($merged) - 1;
            if ($last >= 0 && $from <= $merged[$last][1]) {
                $merged[$last][1] = max($to, $merged[$last][1]);
            } else {
                $merged[] = [$from, $to];
            }
        }

        return $merged;
    }
}
