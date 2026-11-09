<?php

namespace App\Console\Commands;

use App\Models\LessonVideoUpload;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CleanupLessonVideoUploads extends Command
{
    protected $signature = 'videos:cleanup-uploads';

    protected $description = 'Muddati tugagan video yuklash bo‘laklarini tozalash';

    public function handle(): int
    {
        LessonVideoUpload::where('expires_at', '<=', now())->select('id')->chunkById(100, function (Collection $uploads): void {
            foreach ($uploads as $upload) {
                DB::transaction(function () use ($upload): void {
                    $locked = LessonVideoUpload::whereKey($upload->id)->where('expires_at', '<=', now())->lockForUpdate()->first();
                    if ($locked) {
                        Storage::disk('local')->deleteDirectory('video-uploads/'.$locked->id);
                        $locked->delete();
                    }
                });
            }
        });

        return self::SUCCESS;
    }
}
