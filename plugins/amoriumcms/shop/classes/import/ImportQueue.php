<?php namespace AmoriumCms\Shop\Classes\Import;

use Db;
use AmoriumCms\Shop\Models\ImportJob;

/**
 * ImportQueue runs the pending product imports one at a time.
 * A second runner waits while a job is still in progress.
 */
class ImportQueue
{
    /**
     * drain imports every pending link, in id order
     */
    public static function drain(): int
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $count = 0;

        while (static::step()) {
            $count++;
        }

        return $count;
    }

    /**
     * step imports the oldest pending link. Returns false when there is nothing to run
     * or another import is already in progress.
     */
    public static function step(): bool
    {
        $job = Db::transaction(function () {
            static::failStale();

            $busy = ImportJob::where('status', ImportJob::PROCESSING)
                ->where('started_at', '>=', now()->subMinutes(10))
                ->lockForUpdate()
                ->exists();

            if ($busy) {
                return null;
            }

            $job = ImportJob::where('status', ImportJob::PENDING)->orderBy('id')->lockForUpdate()->first();

            if (!$job) {
                return null;
            }

            $job->status = ImportJob::PROCESSING;
            $job->started_at = now();
            $job->save();

            return $job;
        });

        if (!$job) {
            return false;
        }

        set_time_limit(0);
        $job->execute();

        return true;
    }

    /**
     * failStale closes an import that stopped without writing its result
     */
    protected static function failStale(): void
    {
        ImportJob::where('status', ImportJob::PROCESSING)
            ->where(function ($query) {
                $query->whereNull('started_at')->orWhere('started_at', '<', now()->subMinutes(10));
            })
            ->update([
                'status' => ImportJob::FAILED,
                'exit_code' => 1,
                'result' => __('The import stopped before it finished.'),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
