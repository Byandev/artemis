<?php

namespace Modules\Courses\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Lesson videos and course covers are uploaded straight to the bucket under a
 * `pending/` prefix and only moved into a media collection once the browser
 * reports the key back. An upload that finishes but never gets attached — the
 * tab was closed, the attach request failed, the form was abandoned — leaves an
 * object nobody references and nothing else ever deletes. This sweeps those up.
 */
class PrunePendingLessonUploadsCommand extends Command
{
    protected $signature = 'courses:prune-pending-uploads
                            {--hours=24 : Delete pending uploads older than this many hours}
                            {--dry-run : List what would be deleted without deleting it}';

    protected $description = 'Delete course uploads that were never attached to a lesson or course';

    /**
     * Everything this command is allowed to touch lives under here — both
     * `pending/lesson-videos/{lesson}` and `pending/course-covers/{workspace}`.
     * Attached files are moved out of this prefix, so anything left under it is
     * unreferenced by definition.
     */
    private const PREFIX = 'pending';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');

        $disk = Storage::disk(config('filesystems.course_media_disk'));
        $cutoff = Carbon::now()->subHours($hours);

        $deleted = 0;
        $bytes = 0;
        $kept = 0;

        foreach ($disk->allFiles(self::PREFIX) as $path) {
            // An upload still in progress must not be pulled out from under
            // the user, so only objects older than the cutoff are eligible.
            $lastModified = Carbon::createFromTimestamp($disk->lastModified($path));

            if ($lastModified->greaterThan($cutoff)) {
                $kept++;

                continue;
            }

            $size = $disk->size($path);

            if ($dryRun) {
                $this->line("would delete {$path} (".$this->humanBytes($size).')');
            } else {
                $disk->delete($path);
            }

            $deleted++;
            $bytes += $size;
        }

        $verb = $dryRun ? 'Would prune' : 'Pruned';

        $this->info("{$verb} {$deleted} abandoned upload(s), ".$this->humanBytes($bytes).
            " — {$kept} newer than {$hours}h left alone.");

        return self::SUCCESS;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024).' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1).' MB';
        }

        return round($bytes / 1024 / 1024 / 1024, 2).' GB';
    }
}
