<?php

namespace Tests\Fixtures\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;

/** A job whose every attempt throws - DatabaseQueueWorkerTest's failure case. */
class AlwaysFailingJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public const MESSAGE = 'AlwaysFailingJob failed on purpose';

    public function handle(): void
    {
        throw new RuntimeException(self::MESSAGE);
    }
}
