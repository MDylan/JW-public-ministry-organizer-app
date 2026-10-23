<?php
// Writes laravel8-payloads.json: `jobs` table rows built by the v1 release's own
// framework (laravel/framework 8.83) and classes, for DatabaseQueueWorkerTest
// (roadmap TODO 60). Not part of the suite; it runs against an extracted v1
// tree, never this one:
//
//     git archive v1 app vendor | tar -x -C <tree>
//     php tests/Fixtures/Queue/generate-laravel8-payloads.php <tree> <out.json> <v1-commit>
//
// Nothing touches a database: models are built in memory with the ids the test
// creates, which is all SerializesModels writes into a payload.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
[$self, $tree, $out, $rev] = $argv;
require $tree.'/vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

$app = new Container;
Container::setInstance($app);
$app->instance('config', new Illuminate\Config\Repository(['app' => ['timezone' => 'Europe/Budapest']]));
Facade::setFacadeApplication($app);

$groupId = 9001; $memberId = 9002; $date = '2030-01-15';

$model = function (string $class, int $id) {
    $m = new $class;
    $m->forceFill(['id' => $id]);
    $m->exists = true;
    $m->setConnection('mysql');
    return $m;
};
$group = $model(App\Models\Group::class, $groupId);
$member = $model(App\Models\User::class, $memberId);

$jobs = [
    new App\Jobs\CalculateDateProcess($groupId, $date, $memberId),
    new App\Jobs\CalulcateUserNameIndexProcess(),
    new App\Jobs\DeleteGroupDataProcess($groupId, false),
    new App\Jobs\GenerateStatProcess($groupId, $date, false),
    new App\Jobs\GroupDayUpdatedProcess($date, $groupId, 3, '08:00', '12:00', $memberId),
    new App\Jobs\UserLogoutFromGroupProcess($group, $member, 'Admin User'),
];

// Queued notifications, wrapped the way the v1 NotificationSender::queueNotification()
// wraps them: a clone per channel, carrying the notification id, with no queue,
// connection, delay or middleware of its own. These four are the ones a host is
// most likely to hold when it upgrades.
$event = ['userName' => 'Admin User', 'groupName' => 'Csoport', 'replyTo' => '', 'date' => $date,
    'newService' => ['start' => $date.' 09:00:00', 'end' => $date.' 10:00:00'], 'reason' => false];
$notifications = [
    new App\Notifications\UserRegisteredNotification(),
    new App\Notifications\EventCreatedNotification($event),
    new App\Notifications\EventDeletedNotification(['userName' => 'Admin User', 'groupName' => 'Csoport', 'date' => $date,
        'oldService' => $event['newService'], 'reason' => 'user_logout']),
    new App\Notifications\Newsletter(['newsletter_id' => '1_'.$memberId, 'subject' => 'Hirlevel',
        'content' => 'Hirlevel tartalma', 'recipients' => 'groupAdmins']),
];
foreach ($notifications as $original) {
    $notification = clone $original;
    $notification->id = Illuminate\Support\Str::uuid()->toString();
    $jobs[] = (new Illuminate\Notifications\SendQueuedNotifications($member, $notification, ['mail']))
        ->onConnection(null)->onQueue(null)->delay(null)->through([]);
}

// The e-mail change confirmation, queued the way v1 queued it: the
// protonemedia/laravel-verify-new-email mailable (ShouldQueue), filled by
// Mail::to()->send() and wrapped by Mailable::queue(). 2.x no longer has the
// class (TODO 33.5), which is what this row is for.
$pending = $model(App\Models\PendingUserEmail::class, 9003);
$mailable = new ProtoneMedia\LaravelVerifyNewEmail\Mail\VerifyNewEmail($pending);
$mailable->to('queue-member-new@example.test')->mailer('smtp');
$jobs[] = new Illuminate\Mail\SendQueuedMailable($mailable);

$queue = (new ReflectionClass(Illuminate\Queue\DatabaseQueue::class))->newInstanceWithoutConstructor();
$prop = new ReflectionProperty(Illuminate\Queue\Queue::class, 'container');
$prop->setAccessible(true);
$prop->setValue($queue, $app);
$create = new ReflectionMethod(Illuminate\Queue\Queue::class, 'createPayload');
$create->setAccessible(true);

$rows = [];
// 2025-10-23: in the past, so the rows are due at once. Between two runs only
// the uuids and UserLogoutFromGroupProcess's `start` (date() in its
// constructor) differ.
$t = 1761177600;
foreach ($jobs as $job) {
    $rows[] = [
        'queue' => 'default',
        'payload' => $create->invoke($queue, $job, 'default', ''),
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => $t,
        'created_at' => $t,
    ];
}

file_put_contents($out, json_encode([
    'generated_by' => 'the v1 release tree (git '.$rev.'), laravel/framework '.Composer\InstalledVersions::getPrettyVersion('laravel/framework').', PHP '.PHP_VERSION,
    'fixture_ids' => ['group' => $groupId, 'member' => $memberId, 'date' => $date],
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo count($rows), " rows\n";
