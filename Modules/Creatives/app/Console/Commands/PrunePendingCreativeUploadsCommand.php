<?php

namespace Modules\Creatives\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Creative files are uploaded straight to the bucket under a `pending/` prefix
 * as soon as they are picked, and only moved into a media collection when the
 * form is saved. A form that is abandoned leaves an object nobody references
 * and nothing else ever deletes. This sweeps those up.
 */
class PrunePendingCreativeUploadsCommand extends Command
{
    protected $signature = 'creatives:prune-pending-uploads
                            {--hours=24 : Delete pending uploads older than this many hours}
                            {--dry-run : List what would be deleted without deleting it}';

    protected $description = 'Delete creative uploads that were never attached to a creative';

    /** Adopted files are moved out of here, so anything left is unreferenced. */
    private const PREFIX = 'pending/creatives';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');

        $disk = Storage::disk(config('filesystems.creative_media_disk'));
        $cutoff = Carbon::now()->subHours($hours);

        $deleted = 0;
        $kept = 0;

        foreach ($disk->allFiles(self::PREFIX) as $path) {
            // An upload still in progress must not be pulled out from under
            // the user, so only objects older than the cutoff are eligible.
            if (Carbon::createFromTimestamp($disk->lastModified($path))->greaterThan($cutoff)) {
                $kept++;

                continue;
            }

            if ($dryRun) {
                $this->line("would delete {$path}");
            } else {
                $disk->delete($path);
            }

            $deleted++;
        }

        $verb = $dryRun ? 'Would prune' : 'Pruned';

        $this->info("{$verb} {$deleted} abandoned upload(s) — {$kept} newer than {$hours}h left alone.");

        return self::SUCCESS;
    }
}
