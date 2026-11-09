<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\LessonVideoUpload;
use App\Models\User;
use App\Models\VideoProgress;
use App\Services\LessonVideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidationValidator;
use Symfony\Component\HttpFoundation\Response;

class LessonVideoController extends Controller
{
    public function __construct(private LessonVideoService $videos) {}

    public function index(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        $this->videos->authorize($user, $lesson);
        $materials = $lesson->materials()->where('type', 'video')->whereNotNull('payload->version')->orderBy('sort_order')->orderBy('id')->get();
        $progress = VideoProgress::where('user_id', $user->id)->whereIn('lesson_material_id', $materials->pluck('id'))->get()->keyBy('lesson_material_id');

        return response()->json(['videos' => $materials->map(fn (LessonMaterial $material): array => $this->videos->videoData($user, $material, $progress->get($material->id))),
            'can_manage' => $user->hasPermission('materials.manage') && $user->hasPermission('lessons.update'),
            'can_track' => $user->hasRole('STUDENT'), 'user_id' => $user->id, 'chunk_bytes' => config('lesson_video.chunk_bytes'), 'max_bytes' => config('lesson_video.max_bytes')])->header('Cache-Control', 'private, no-store');
    }

    public function youtube(Request $request, Lesson $lesson): JsonResponse
    {
        $this->manage($request);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'url' => ['required', 'string', 'max:500'],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:21600']]);
        $url = parse_url($data['url']);
        $id = null;
        if (is_array($url) && ($url['scheme'] ?? '') === 'https') {
            $host = strtolower($url['host'] ?? '');
            $path = $url['path'] ?? '';
            if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
                $id = trim($path, '/');
            } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'www.youtube-nocookie.com'], true)) {
                parse_str($url['query'] ?? '', $query);
                $id = $path === '/watch' ? ($query['v'] ?? null) : (preg_match('~^/(?:embed|shorts)/([\w-]{11})/?$~', $path, $match) ? $match[1] : null);
            }
        }
        if (! is_string($id) || ! preg_match('/^[a-zA-Z0-9_-]{11}$/', $id)) {
            throw ValidationException::withMessages(['url' => 'Yaroqli HTTPS YouTube video havolasini kiriting.']);
        }
        $material = $lesson->materials()->create(['title' => $data['title'], 'type' => 'video', 'sort_order' => 0,
            'payload' => ['provider' => 'youtube', 'youtube_id' => $id, 'version' => (string) Str::uuid(), 'duration_seconds' => (int) $data['duration_seconds'], 'offline_allowed' => false]]);

        return response()->json(['id' => $material->id], 201);
    }

    public function beginUpload(Request $request, Lesson $lesson): JsonResponse
    {
        $this->manage($request);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'size_bytes' => ['required', 'integer', 'min:32', 'max:'.config('lesson_video.max_bytes')],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:21600'], 'offline_allowed' => ['required', 'boolean']]);
        abort_if(LessonVideoUpload::where('user_id', $request->user()->id)->whereNull('lesson_material_id')->where('expires_at', '>', now())->count() >= 4, 429, 'Avval boshlangan yuklashni tugating yoki bekor qiling.');
        $upload = LessonVideoUpload::create($data + ['id' => (string) Str::uuid(), 'user_id' => $request->user()->id, 'lesson_id' => $lesson->id, 'expires_at' => now()->addDay()]);

        return response()->json(['upload_id' => $upload->id, 'chunk_bytes' => config('lesson_video.chunk_bytes')], 201);
    }

    public function chunk(Request $request, LessonVideoUpload $upload): JsonResponse
    {
        $this->authorizeUpload($request, $upload);
        $data = $request->validate(['index' => ['required', 'integer', 'min:0', 'max:511'], 'chunk' => ['required', 'file', 'max:512']]);

        return DB::transaction(function () use ($upload, $data): JsonResponse {
            $upload = LessonVideoUpload::whereKey($upload->id)->lockForUpdate()->firstOrFail();
            abort_if($upload->expires_at->isPast(), 410);
            if ($upload->lesson_material_id) {
                return response()->json(['received' => true]);
            }
            $chunkSize = (int) config('lesson_video.chunk_bytes');
            $offset = (int) $data['index'] * $chunkSize;
            $expected = min($chunkSize, $upload->size_bytes - $offset);
            if ($expected <= 0 || $data['chunk']->getSize() !== $expected) {
                throw ValidationException::withMessages(['chunk' => 'Video bo‘lagi o‘lchami mos emas.']);
            }
            $stream = fopen($data['chunk']->getRealPath(), 'rb');
            try {
                abort_unless(Storage::disk('local')->put('video-uploads/'.$upload->id.'/'.$data['index'], $stream), 503, 'Video bo‘lagi saqlanmadi.');
            } finally {
                fclose($stream);
            }

            return response()->json(['received' => true]);
        });
    }

    public function finishUpload(Request $request, LessonVideoUpload $upload): JsonResponse
    {
        $this->authorizeUpload($request, $upload);
        $material = DB::transaction(function () use ($upload): LessonMaterial {
            $upload = LessonVideoUpload::whereKey($upload->id)->lockForUpdate()->firstOrFail();
            abort_if($upload->expires_at->isPast(), 410);
            if ($upload->lesson_material_id) {
                return LessonMaterial::findOrFail($upload->lesson_material_id);
            }
            $disk = (string) config('lesson_video.disk');
            abort_unless(in_array($disk, ['local', 's3'], true), 503, 'Video saqlash diski noto‘g‘ri sozlangan.');
            $file = tmpfile();
            abort_unless($file !== false, 503);
            $path = 'lesson-videos/'.$upload->lesson_id.'/'.$upload->id.'.mp4';
            try {
                $parts = (int) ceil($upload->size_bytes / config('lesson_video.chunk_bytes'));
                for ($index = 0; $index < $parts; $index++) {
                    $part = 'video-uploads/'.$upload->id.'/'.$index;
                    if (! Storage::disk('local')->exists($part)) {
                        throw ValidationException::withMessages(['upload' => 'Video to‘liq yuklanmagan. Qayta urinib ko‘ring.']);
                    }
                    $source = Storage::disk('local')->readStream($part);
                    try {
                        stream_copy_to_stream($source, $file);
                    } finally {
                        fclose($source);
                    }
                }
                $metadata = stream_get_meta_data($file);
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($metadata['uri']);
                if (fstat($file)['size'] !== $upload->size_bytes || ! in_array($mime, ['video/mp4', 'application/mp4'], true)) {
                    throw ValidationException::withMessages(['upload' => 'MP4 (H.264/AAC) video faylini tanlang.']);
                }
                rewind($file);
                $options = ['visibility' => 'private', 'ContentType' => 'video/mp4'];
                if ($disk === 's3') {
                    $acl = config('lesson_video.s3_acl');
                    abort_unless(in_array($acl, ['private', 'bucket-owner-full-control'], true), 503, 'Video uchun private S3 ruxsatini sozlang.');
                    $options['ACL'] = $acl;
                }
                abort_unless(Storage::disk($disk)->put($path, $file, $options), 503, 'Video saqlanmadi.');
                try {
                    $material = LessonMaterial::create(['lesson_id' => $upload->lesson_id, 'title' => $upload->title, 'type' => 'video', 'path' => $path,
                        'payload' => ['provider' => 'upload', 'disk' => $disk, 'mime_type' => 'video/mp4', 'version' => $upload->id,
                            'duration_seconds' => $upload->duration_seconds, 'size_bytes' => $upload->size_bytes, 'offline_allowed' => $upload->offline_allowed]]);
                    $upload->update(['lesson_material_id' => $material->id]);
                } catch (\Throwable $error) {
                    Storage::disk($disk)->delete($path);
                    throw $error;
                }
            } finally {
                fclose($file);
            }
            Storage::disk('local')->deleteDirectory('video-uploads/'.$upload->id);

            return $material;
        }, attempts: 3);

        return response()->json(['id' => $material->id], 201);
    }

    public function cancelUpload(Request $request, LessonVideoUpload $upload): JsonResponse
    {
        $this->authorizeUpload($request, $upload);
        DB::transaction(function () use ($upload): void {
            $locked = LessonVideoUpload::whereKey($upload->id)->lockForUpdate()->firstOrFail();
            Storage::disk('local')->deleteDirectory('video-uploads/'.$locked->id);
            $locked->delete();
        });

        return response()->json(['cancelled' => true]);
    }

    public function destroy(Request $request, LessonMaterial $material): JsonResponse
    {
        $this->manage($request);
        abort_unless($material->type === 'video', 404);
        DB::transaction(function () use ($material): void {
            LessonVideoUpload::where('lesson_material_id', $material->id)->delete();
            if (($material->payload['provider'] ?? '') === 'upload') {
                abort_unless(Storage::disk($material->payload['disk'])->delete($material->path), 503, 'Video fayli o‘chirilmadi.');
            }
            $material->delete();
        });

        return response()->json(['deleted' => true]);
    }

    public function progress(Request $request, LessonMaterial $material): JsonResponse
    {
        $this->videos->authorizeMaterial($request->user(), $material);
        $validator = Validator::make($request->all(), ['owner_id' => ['required', 'integer', Rule::in([$request->user()->id])], 'position_seconds' => ['required', 'integer', 'min:0', 'max:21600'],
            'observed_at' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(5)->toISOString()],
            'ranges' => ['present', 'array', 'list', 'max:21600'], 'ranges.*' => ['array', 'list', 'size:2'], 'ranges.*.*' => ['required', 'integer', 'min:0', 'max:21600']]);
        $validator->after(function (ValidationValidator $validator) use ($request): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ($request->input('ranges') as [$from, $to]) {
                if ($to < $from) {
                    $validator->errors()->add('ranges', 'Video ko‘rish oralig‘i noto‘g‘ri.');
                }
            }
        });
        $progress = $this->videos->saveProgress($request->user(), $material, $validator->validate());

        return response()->json(['progress' => $this->videos->videoData($request->user(), $material, $progress)['progress']]);
    }

    public function stream(Request $request, LessonMaterial $material, User $user): Response
    {
        $this->videos->authorizeMaterial($user, $material);
        $payload = $material->payload;
        abort_unless($payload['provider'] === 'upload' && hash_equals($payload['version'], (string) $request->query('version')), 404);
        $download = $request->boolean('download');
        abort_if($download && ! $payload['offline_allowed'], 403);
        $filename = 'lesson-video-'.$material->id.'.mp4';
        $disposition = ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"';
        $disk = Storage::disk($payload['disk']);
        abort_unless($disk->exists($material->path), 404);
        if ($payload['disk'] === 's3') {
            return redirect()->away($disk->temporaryUrl($material->path, $download ? now()->addDays(7) : now()->addHours(6), ['ResponseContentType' => 'video/mp4', 'ResponseContentDisposition' => $disposition]));
        }

        return response()->file($disk->path($material->path), ['Content-Type' => 'video/mp4', 'Content-Disposition' => $disposition, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Accept-Ranges' => 'bytes']);
    }

    private function manage(Request $request): void
    {
        abort_unless($request->user()->hasPermission('materials.manage') && $request->user()->hasPermission('lessons.update'), 403);
    }

    private function authorizeUpload(Request $request, LessonVideoUpload $upload): void
    {
        $this->manage($request);
        abort_unless($upload->user_id === $request->user()->id, 404);
        abort_if($upload->expires_at->isPast(), 410, 'Yuklash muddati tugagan. Yangi yuklashni boshlang.');
    }
}
