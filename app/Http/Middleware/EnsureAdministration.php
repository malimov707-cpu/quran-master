<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Schedule;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministration
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->hasRole('SUPER_ADMIN', 'CENTER_ADMIN'), 403);
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        return DB::transaction(function () use ($request, $next): Response {
            $response = $next($request);
            $resource = match ($request->segment(4)) {
                'courses' => ['course', Course::class], 'lessons' => ['lesson', Lesson::class], 'groups' => ['group', Group::class], 'schedules' => ['schedule', Schedule::class], default => null
            };
            if ($resource && $response->isSuccessful()) {
                [$name, $model] = $resource;
                $bound = $request->route($name);
                $body = $response instanceof JsonResponse ? $response->getData(true) : [];
                $id = $bound instanceof Model ? $bound->getKey() : ($body['record']['id'] ?? $body['course']['id'] ?? null);
                $operation = $request->segment(6) === 'students' ? 'members.updated' : ($request->segment(6) === 'attendance' ? 'attendance.updated' : ($request->isMethod('POST') ? 'created' : 'updated'));
                DB::table('audit_logs')->insert(['user_id' => $request->user()->id, 'action' => $name.'.'.$operation, 'subject_type' => $model, 'subject_id' => $id, 'created_at' => now()]);
            }

            return $response;
        });
    }
}
