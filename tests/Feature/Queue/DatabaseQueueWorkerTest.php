<?php

namespace Tests\Feature\Queue;

use App\Jobs\CalculateDateProcess;
use App\Jobs\CalulcateUserNameIndexProcess;
use App\Jobs\DeleteGroupDataProcess;
use App\Jobs\GenerateStatProcess;
use App\Jobs\GroupDayUpdatedProcess;
use App\Jobs\UserLogoutFromGroupProcess;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\FailedJobMonitor\Notification as FailedJobNotification;
use Tests\Feature\FeatureTestCase;
use Tests\Fixtures\Jobs\AlwaysFailingJob;

/**
 * Roadmap TODO 60: the jobs run on the queue production uses, under Laravel 11.
 *
 * phpunit.xml sets QUEUE_CONNECTION=sync, so every other job test runs
 * handle() inside the dispatch. Production runs the `database` connection and
 * a scheduled `queue:work` (routes/console.php). These cases push onto the
 * `jobs` table and let the framework's own worker pop, unserialize and run
 * each job, the way a host does.
 *
 * The laravel8* providers are the upgrade case. A host may still hold rows the
 * 1.x release (laravel/framework 8.83) wrote when 2.0.0 arrives, and the new
 * worker picks them up after `php artisan up`.
 * tests/Fixtures/Queue/laravel8-payloads.json holds one such row per job, four
 * queued notifications, and the protonemedia e-mail confirmation 2.x can no
 * longer run. It was produced by generate-laravel8-payloads.php (same
 * directory) from the v1 tree's own vendor/ and app/ - so both the framework's
 * payload format and the classes' serialized properties are the old
 * release's, not a reconstruction:
 *
 *     git archive v1 app vendor | tar -x -C <tree>
 *     php tests/Fixtures/Queue/generate-laravel8-payloads.php <tree> <out.json> <rev>
 */
class DatabaseQueueWorkerTest extends FeatureTestCase
{
    private const GROUP_ID = 9001;
    private const MEMBER_ID = 9002;
    private const DATE = '2030-01-15';

    private Group $group;
    private User $member;
    private Event $event;

    /** @var list<string> */
    private array $processed = [];

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('queue.default', 'database');

        // The ids are the fixture's: its payloads name them.
        $this->group = $this->createGroup(['id' => self::GROUP_ID]);
        $this->member = $this->createUser(['id' => self::MEMBER_ID, 'email' => 'queue-member@example.test']);
        $this->attachUserToGroup($this->member, $this->group);
        $this->actingAs($this->member);

        GroupDate::factory()->create([
            'group_id' => self::GROUP_ID,
            'date' => self::DATE,
            'date_start' => self::DATE.' 08:00:00',
            'date_end' => self::DATE.' 12:00:00',
            'date_min_time' => 60,
            'run_job' => 1,
        ]);

        $this->event = Event::factory()->create([
            'group_id' => self::GROUP_ID,
            'user_id' => self::MEMBER_ID,
            'day' => self::DATE,
            'start' => self::DATE.' 09:00:00',
            'end' => self::DATE.' 10:00:00',
            'status' => 1,
            'accepted_at' => now(),
            'accepted_by' => self::MEMBER_ID,
        ]);

        Queue::after(fn (JobProcessed $e) => $this->processed[] = $e->job->resolveName());

        // The fixture's rows and the dispatches above must start from an
        // empty table; setUp's own writes queue nothing on `database`.
        DB::table('jobs')->delete();
    }

    public static function jobClasses(): array
    {
        return array_map(fn ($class) => [$class], [
            'CalculateDateProcess' => CalculateDateProcess::class,
            'CalulcateUserNameIndexProcess' => CalulcateUserNameIndexProcess::class,
            'DeleteGroupDataProcess' => DeleteGroupDataProcess::class,
            'GenerateStatProcess' => GenerateStatProcess::class,
            'GroupDayUpdatedProcess' => GroupDayUpdatedProcess::class,
            'UserLogoutFromGroupProcess' => UserLogoutFromGroupProcess::class,
        ]);
    }

    public static function laravel8JobRows(): array
    {
        return self::laravel8Rows('App\\Jobs\\');
    }

    public static function laravel8NotificationRows(): array
    {
        return self::laravel8Rows('App\\Notifications\\');
    }

    private static function laravel8Rows(string $namespace): array
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../../Fixtures/Queue/laravel8-payloads.json'), true);

        $rows = [];
        foreach ($fixture['rows'] as $row) {
            $class = json_decode($row['payload'], true)['displayName'];
            if (str_starts_with($class, $namespace)) {
                $rows[class_basename($class)] = [$row];
            }
        }

        return $rows;
    }

    #[DataProvider('jobClasses')]
    public function test_each_job_runs_on_the_database_worker(string $class): void
    {
        Queue::connection('database')->push($this->makeJob($class));
        $this->assertSame(1, DB::table('jobs')->count());

        $this->work();

        $this->assertRanCleanly($class);
    }

    #[DataProvider('laravel8JobRows')]
    public function test_a_job_the_1x_release_queued_runs_on_this_worker(array $row): void
    {
        $class = $this->insertLaravel8Row($row);

        $this->work();

        $this->assertRanCleanly($class);
    }

    #[DataProvider('laravel8NotificationRows')]
    public function test_a_notification_the_1x_release_queued_is_delivered_by_this_worker(array $row): void
    {
        // Most of what sits in a host's `jobs` table is mail: 24 of the
        // notifications are ShouldQueue. Not faked here - the queued
        // notification is sent through the mail channel into phpunit.xml's
        // array mailer, so Laravel 11 renders the old release's object.
        Notification::swap(new \Illuminate\Notifications\ChannelManager($this->app));
        $transport = app('mailer')->getSymfonyTransport();
        $transport->flush();

        $class = $this->insertLaravel8Row($row);

        $this->work();

        $this->assertRanCleanly($class);

        $messages = $transport->messages();
        $this->assertCount(1, $messages, "{$class} sent no mail.");
        $email = $messages[0]->getOriginalMessage();
        $this->assertSame(['queue-member@example.test'], array_map(fn ($a) => $a->getAddress(), $email->getTo()));
        $this->assertNotSame('', trim((string) $email->getSubject()));
    }

    public function test_a_failed_job_is_recorded_and_reported_once_after_the_scheduled_tries(): void
    {
        // The worker's options are read from the schedule, not restated, so
        // a change to --tries or --backoff there is measured here.
        $options = $this->scheduledWorkerOptions();
        $this->assertSame('3', $options['--tries']);
        $this->assertSame('20', $options['--backoff']);

        Queue::connection('database')->push(new AlwaysFailingJob());

        foreach ([1, 2] as $attempt) {
            $this->work($options);

            // Released for a retry: still queued, not failed, nobody told.
            $this->assertSame($attempt, (int) DB::table('jobs')->value('attempts'));
            $this->assertSame(0, DB::table('failed_jobs')->count());
            Notification::assertNothingSent();

            $this->travel((int) $options['--backoff'] + 1)->seconds();
        }

        $this->work($options);

        $this->assertSame(0, DB::table('jobs')->count());
        $failed = DB::table('failed_jobs')->sole();
        $this->assertNotEmpty($failed->uuid);
        $this->assertStringContainsString(AlwaysFailingJob::MESSAGE, $failed->exception);

        Notification::assertSentTimes(FailedJobNotification::class, 1);
        Notification::assertSentTo(
            new \Spatie\FailedJobMonitor\Notifiable(),
            FailedJobNotification::class,
            fn (FailedJobNotification $n) => $n->getEvent()->job->resolveName() === AlwaysFailingJob::class
        );
    }

    public function test_a_verification_mail_the_removed_package_queued_fails_and_is_reported(): void
    {
        // Characterization of the one 1.x row 2.x cannot run. v1 queued the
        // e-mail confirmation as protonemedia/laravel-verify-new-email's own
        // mailable; TODO 33.5 replaced the package with App\Mail and section 2
        // of the upgrade guide deletes its vendor directory. The row is not
        // lost silently: it fails after the scheduled worker's tries, lands in
        // failed_jobs, and the monitor mails once. The user has to ask for the
        // confirmation again (upgrade-guide.md section 5, TODO 60).
        $row = collect(self::laravel8Rows('ProtoneMedia\\'))->sole()[0];
        $this->assertFalse(class_exists('ProtoneMedia\\LaravelVerifyNewEmail\\Mail\\VerifyNewEmail'));

        $options = $this->scheduledWorkerOptions();
        $this->insertLaravel8Row($row);

        foreach ([1, 2] as $attempt) {
            $this->work($options);
            $this->assertSame($attempt, (int) DB::table('jobs')->value('attempts'));
            $this->travel((int) $options['--backoff'] + 1)->seconds();
        }
        $this->work($options);

        $this->assertSame(0, DB::table('jobs')->count());
        $exception = DB::table('failed_jobs')->sole()->exception;
        $this->assertStringContainsString('tried to call a method on an incomplete object', $exception);
        $this->assertStringContainsString('ProtoneMedia\\LaravelVerifyNewEmail\\Mail\\VerifyNewEmail', $exception);
        Notification::assertSentTimes(FailedJobNotification::class, 1);
    }

    public function test_a_throwing_notification_is_retried_for_two_days_before_the_monitor_hears_of_it(): void
    {
        // Characterization, not a fix. The queued notifications set their own
        // $tries = 2880 and $backoff = 60, and a job's own values win over the
        // worker's --tries=3 --backoff=20. So a notification that throws on
        // every attempt is retried once a minute for 48 hours, and only the
        // 2880th failure reaches failed_jobs and the monitor. Newsletter with
        // an unknown recipients value is such a notification: toMail() reads
        // an undefined $recipients.
        $this->freezeTime();
        Notification::swap(new \Illuminate\Notifications\ChannelManager($this->app));
        $this->member->notify(new \App\Notifications\Newsletter([
            'newsletter_id' => '1_'.self::MEMBER_ID,
            'subject' => 'Hirlevel',
            'content' => 'Hirlevel tartalma',
            'recipients' => 'nobody',
        ]));

        $payload = json_decode(DB::table('jobs')->sole()->payload, true);
        $this->assertSame(2880, $payload['maxTries']);
        $this->assertSame('60', $payload['backoff']); // the framework writes it as a string

        Notification::fake();
        $this->work($this->scheduledWorkerOptions());

        $row = DB::table('jobs')->sole();
        $this->assertSame(1, (int) $row->attempts);
        $this->assertSame(now()->addSeconds(60)->getTimestamp(), (int) $row->available_at);
        $this->assertSame(0, DB::table('failed_jobs')->count());
        Notification::assertNothingSent();
    }

    public function test_the_failure_mail_is_rendered_and_sent_to_the_configured_address(): void
    {
        // Not faked: the notification goes through the mail channel into
        // phpunit.xml's array mailer, so Laravel 11 renders the message.
        // setUp's fake is undone with a fresh manager, the one the
        // container would have built.
        Notification::swap(new \Illuminate\Notifications\ChannelManager($this->app));
        config()->set('failed-job-monitor.mail.to', 'hibak@example.test');

        $transport = app('mailer')->getSymfonyTransport();
        $transport->flush();

        Queue::connection('database')->push(new AlwaysFailingJob());
        $this->work(['--tries' => 1]);

        $messages = $transport->messages();
        $this->assertCount(1, $messages);

        $email = $messages[0]->getOriginalMessage();
        $this->assertSame(['hibak@example.test'], array_map(fn ($a) => $a->getAddress(), $email->getTo()));
        $this->assertSame('A job failed at '.config('app.url'), $email->getSubject());
        $this->assertStringContainsString(AlwaysFailingJob::MESSAGE, $email->getTextBody());
    }

    /** Queue one fixture row as it is; returns the class the worker will run. */
    private function insertLaravel8Row(array $row): string
    {
        $payload = json_decode($row['payload'], true);

        // Evidence that the row really is the old release's: Laravel 11's
        // worker reads these keys, and the fixture carries exactly the
        // Laravel 8.83 set.
        $this->assertSame(
            ['backoff', 'data', 'displayName', 'failOnTimeout', 'job', 'maxExceptions', 'maxTries', 'retryUntil', 'timeout', 'uuid'],
            collect($payload)->keys()->sort()->values()->all()
        );

        DB::table('jobs')->insert($row);

        return $payload['displayName'];
    }

    private function makeJob(string $class): object
    {
        return match ($class) {
            CalculateDateProcess::class => new CalculateDateProcess(self::GROUP_ID, self::DATE, self::MEMBER_ID),
            CalulcateUserNameIndexProcess::class => new CalulcateUserNameIndexProcess(),
            DeleteGroupDataProcess::class => new DeleteGroupDataProcess(self::GROUP_ID, false),
            GenerateStatProcess::class => new GenerateStatProcess(self::GROUP_ID, self::DATE, false),
            GroupDayUpdatedProcess::class => new GroupDayUpdatedProcess(self::DATE, self::GROUP_ID, 3, '08:00', '12:00', self::MEMBER_ID),
            UserLogoutFromGroupProcess::class => new UserLogoutFromGroupProcess($this->group, $this->member, 'Admin User'),
        };
    }

    /** One pass of the framework's worker over the `database` connection. */
    private function work(array $options = []): void
    {
        $this->artisan('queue:work', array_merge($options, [
            'connection' => 'database',
            '--once' => true,
            '--sleep' => 0,
        ]))->assertExitCode(0);
    }

    private function assertRanCleanly(string $class): void
    {
        $failed = DB::table('failed_jobs')->value('exception');
        $this->assertNull($failed, "{$class} failed on the worker: ".strtok((string) $failed, "\n"));
        $this->assertSame(0, DB::table('jobs')->count(), "{$class} is still queued.");
        $this->assertSame([$class], $this->processed);

        // Where a job's effect is cheap to see, see it: a row that ran
        // without doing its work would pass the three lines above.
        match ($class) {
            GenerateStatProcess::class => $this->assertSame(0, (int) GroupDate::where('group_id', self::GROUP_ID)->value('run_job')),
            DeleteGroupDataProcess::class => $this->assertSame(0, GroupDate::where('group_id', self::GROUP_ID)->count()),
            UserLogoutFromGroupProcess::class => $this->assertNull(Event::find($this->event->id)),
            default => null,
        };
    }

    /** The scheduled `queue:work` command's options, as `--name => value`. */
    private function scheduledWorkerOptions(): array
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'queue:work'));
        $this->assertNotNull($event, 'The schedule no longer runs queue:work.');

        preg_match_all('/(--[a-z-]+)=(\S+)/', $event->command, $m, PREG_SET_ORDER);

        return collect($m)->mapWithKeys(fn ($o) => [$o[1] => trim($o[2], "'\"")])->all();
    }
}
