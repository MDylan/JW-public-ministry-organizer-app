# Notifications Documentation

## Overview

Notification classes are located in `app/Notifications`.

### Delivery model

- Channel is almost exclusively `mail`.
- Most notifications implement `ShouldQueue`.
- Most queued notifications define retry settings (`$tries = 2880`, `$backoff = 60`).

### Opt-out capable notifications

The `App\Traits\OptOutable` trait conditionally cancels delivery by returning an empty `via()` array.

Used by:

- `EventDeletedNotification`
- `EventDeletedAdminsNotification`
- `GroupPriorityMessageNotification`
- `UserProfileChangedNotification`

### Three kinds of coverage

`tests/Unit/Notifications/NotificationRegressionTest.php` covers the **mail contract** of every class: whether it queues, whether `mail` is among its channels, and whether `toMail()` builds a `MailMessage`. That says nothing about whether the notification is ever *sent*. Its data provider is itself an assertion — `test_every_notification_class_has_a_mail_contract` reads this directory and fails if a class is added without a contract row (roadmap TODO 15).

The "Covered by" column below records the **dispatch trigger** test — the one that would fail if a notification silently stopped going out. Settled in roadmap TODO 11. **Every notification now has both kinds of coverage, with no exceptions**: the last gap, `TestNotification`'s setup-flow dispatch site, was closed by TODO 12, and the one class that had no dispatch site at all was deleted in TODO 15.

`tests/Feature/Mail/NotificationAddressingTest.php` adds the third kind, and roadmap TODO 36 added it because the first two together still left a gap: neither ever renders a `MailMessage` into a `Symfony\Component\Mime\Email`. **Rendering is where the mailer's address handling applies**, so on the hop that replaced SwiftMailer with Symfony Mailer, the step most likely to break was the one nothing looked at. That file sends the five address-carrying notifications through the `array` transport and reads `getTo()` / `getReplyTo()` / `getBcc()` off the finished message. It covers those five only — the other twenty address nothing beyond their recipient, and the contract test already covers them.

## Notification Catalog

## Event Domain

| Notification | Queued | Triggered From | Covered by | Purpose |
|---|---|---|---|---|
| `EventCreatedNotification` | Yes | `EventObserver::created` | `ObserverRegressionTest`, `ObserverCauserTest` | Informs assigned user when someone else creates their event. |
| `EventUpdatedNotification` | Yes | `EventObserver::updated`, `CalculateDatesEvents::generate` | `ObserverRegressionTest`, `GroupDayTemplateCleanupTest` | Informs users about schedule/time changes. |
| `EventStatusChangedNotification` | Yes | `EventObserver::updated` | `ObserverRegressionTest` | Informs users when event status changes (accept/reject flow). |
| `EventDeletedNotification` | Yes | `EventObserver::deleted`, `CalculateDatesEvents`, `UserLogoutFromGroupProcess` | `ObserverRegressionTest`, `SystemCauserJobsTest`, `UserLogoutFromGroupProcessTest`, `GroupDayTemplateCleanupTest` | Informs users that an event was removed. |
| `EventDeletedAdminsNotification` | Yes | `EventObserver::deleted` (accepted event path) | `ObserverRegressionTest`, `NotificationTriggerRegressionTest` | Notifies group editors/admins about accepted-event deletions. |

## Group Domain

| Notification | Queued | Triggered From | Covered by | Purpose |
|---|---|---|---|---|
| `GroupUserAddedNotification` | Yes | `GroupUserMoves::attach`, `Livewire\Groups\ListUsers` | `DomainServiceRegressionTest`, `CriticalUserFlowsTest`, `GroupUserInviteTest`, `GroupHierarchyLinkTest` | Notifies user they were attached to a group. |
| `GroupUserLogoutNotification` | Yes | `GroupUserMoves::detach` | `CriticalUserFlowsTest`, `GroupHierarchyLinkTest` | Notifies user they were removed from a group. |
| `GroupPriorityMessageNotification` | Yes | `Livewire\Groups\Messages` | `GroupMessagesTest` | Sends high-priority group board messages. |
| `GroupParentGroupAttachedNotification` | Yes | `Livewire\Groups\ListUsers::linkToGroup` | `GroupHierarchyLinkTest` | Notifies admins when parent-child group link is created. |
| `GroupParentGroupDetachedNotification` | Yes | `Livewire\Groups\ListUsers::detachParentGroup` | `GroupHierarchyLinkTest` | Notifies admins when parent-child group link is removed. |
| `Newsletter` | Yes | `Console\Commands\SendDueNewsletters` | `NewsletterAndGroupChangeCommandsTest` | Sends scheduled admin newsletters to segmented audiences. |

## User / Account Domain

| Notification | Queued | Triggered From | Covered by | Purpose |
|---|---|---|---|---|
| `UserRegisteredNotification` | Yes | `Actions\Fortify\CreateNewUser` | `NotificationTriggerRegressionTest` | Registration email with verification link. |
| `FinishRegistration` | Yes | `Livewire\Groups\ListUsers::createUser` | `GroupUserInviteTest` | Invitation-style email to complete account registration. |
| `FinishRegistrationSuccessNotification` | Yes | `Http\Controllers\FinishRegistration` | `CriticalUserFlowsTest` | Confirms successful finish-registration flow. |
| `UserEmailChangedNotification` | Yes | `Actions\Fortify\UpdateUserProfileInformation` | `NotificationTriggerRegressionTest`, `NewEmail\PendingEmailFlowTest` | Alerts the **old** address that a new one is pending. Goes out alongside the verification mail below. |
| `LoginData` | Yes | `Livewire\Groups\ListUsers::updateUser` | `GroupRoleAssignmentTest` | Sends login details/entry data to created users. |
| `UserProfileChangedNotification` | Yes | `Livewire\Groups\ListUsers::updateUser` | `GroupRoleAssignmentTest` | Notifies user profile was modified by admin/editor. |
| `UserProfileRenewalNotification` | Yes | `Livewire\Groups\ListUsers::userRenewal` | `GroupUserInviteTest` | Asks user to renew/update profile data. |
| `UserProfileRenewalAdminNotification` | Yes | `Livewire\Groups\ListUsers::userRenewal` | `GroupUserInviteTest` | Notifies group editors that renewal request was initiated. |
| `deletePersonalDataNotification` | Yes | `Http\Controllers\deletePersonalDataController` | `NotificationTriggerRegressionTest` | Sends signed confirmation link before personal-data deletion. |

## Role / System Domain

| Notification | Queued | Triggered From | Covered by | Purpose |
|---|---|---|---|---|
| `NewAdminNotification` | Yes | `UserObserver::adminAdded` | `NotificationTriggerRegressionTest` | Alerts existing admins about a new main admin. |
| `UserRoleIsGroupCreatorNotification` | Yes | `UserObserver::updated` | `ObserverRegressionTest` | Informs user when promoted to group creator role. |
| `UserWillBeAnonymizeNotification` | Yes | `Console\Commands\NotifyUpcomingAnonymization` | `GdprCommandsTest` | Warns users before anonymization deadline. |
| `UserWillBeAnyonimizeAdminNotification` | Yes | `Console\Commands\NotifyUpcomingAnonymization` | `GdprCommandsTest` | Warns group admins about users nearing anonymization. |

## Utility / Testing

| Notification | Queued | Triggered From | Covered by | Purpose |
|---|---|---|---|---|
| `TestNotification` | No | `Livewire\Admin\Settings`, `Setup\MailController` | `AdminSettingsTest` and `Setup\SetupMailTest` — both dispatch sites are covered | SMTP/mail configuration test notification. |

## Mailables (not Notifications)

Two mail classes reach users without going through the notification system at all. They are
dispatched from `App\Support\Email\MustVerifyNewEmail::sendPendingEmailVerificationMail()`, which
picks between them on `hasVerifiedEmail()`. Both are addressed to the **new** address, both are
configurable in `config/verify-new-email.php`, and both are `ShouldQueue`. They were
`protonemedia/laravel-verify-new-email` classes until roadmap TODO 33.5 replaced the package.

| Mailable | View | Sent when | Covered by |
|---|---|---|---|
| `App\Mail\VerifyNewEmail` | `resources/views/emails/verifyNewEmail.blade.php` | The user's current address is already verified | `NewEmail\PendingEmailFlowTest` |
| `App\Mail\VerifyFirstEmail` | `resources/views/emails/verifyFirstEmail.blade.php` | The user's current address is **not** verified | `NewEmail\PendingEmailFlowTest` |

Two consequences worth knowing:

- **They are `ShouldQueue`, so under `Mail::fake()` they must be asserted with `assertQueued()`,
  not `assertSent()`** — `MailFake::send()` diverts a queueable mailable before recording it. The
  queue connection is `sync`, so in production they still go out in the same request.
- **`verifyFirstEmail.blade.php` used to be the package's untranslated English stub**, while its
  sibling was fully `@lang()`-ed. In a 22-locale application that was a real gap, and it was
  reachable — any user who never verified their original address got the English mail. **Fixed by
  roadmap TODO 33.5**: it now renders `email.verifyFirstEmail.line_1` / `line_2`, translated in
  `hu`, `en` and `de` — the only three locales that carry an application-level `email.php` at all.
  The remaining 19 locale directories are installer stubs; the translation editor
  (`admin.translate`) is how they get filled. Pinned by `NewEmail\PendingEmailKnownGapsTest`, both
  as a source assertion and as a rendered-body assertion.

## Environment dependencies

**Fixed in v1-patch (roadmap TODO 28).** Six notifications used to read `env()` at **runtime**, outside a config file. When configuration is cached (`php artisan config:cache`) Laravel never loads the `.env` file, so those calls returned `null`. Two severity classes, and the difference mattered:

| Notification | Call before | Effect once `env()` returned null |
|---|---|---|
| `EventCreatedNotification:68` | `->replyTo(env('MAIL_FROM_ADDRESS'))` | **Sending failed** |
| `EventDeletedNotification:66` | `->replyTo(env('MAIL_FROM_ADDRESS'))` | **Sending failed** |
| `EventStatusChangedNotification:71` | `->replyTo(env('MAIL_FROM_ADDRESS'))` | **Sending failed** |
| `EventUpdatedNotification:75` | `->replyTo(env('MAIL_FROM_ADDRESS'))` | **Sending failed** |
| `UserRoleIsGroupCreatorNotification:45` | `->bcc(env('MAIL_FROM_ADDRESS'))` | **Sending failed** |
| `UserWillBeAnonymizeNotification:62` | `env('APP_NAME')` in a translation placeholder | Mail still went out, with an empty app name |

All six now read `config('mail.from.address')` / `config('app.name')` — the same values through the cacheable path. The `replyTo`/`bcc` fallback only applies when the group's own `replyTo` is blank, so the failure was data-dependent rather than universal. The measured failure was `Swift_RfcComplianceException: Address in mailbox given [] does not comply with RFC 2822, 3.6.2.`; **Phase 4 (TODO 34) has since swapped SwiftMailer for Symfony Mailer**, where the same condition surfaces as `Symfony\Component\Mime\Exception\RfcComplianceException`. That was a forecast when this line was written - and it named the wrong phase, Phase 5 - so it is now a statement of fact: the notification suite runs on Symfony Mailer and is green, which is the evidence that the TODO 28 fix holds under the stricter mailer.

**TODO 36 finished the measurement those five deserved.** `NotificationAddressingTest` renders each of them and asserts the address that actually lands, on three inputs: a real group `replyTo`, a blank one, and a **null** one. The null case is the one worth having — `groups.replyTo` is a nullable `text` column and every producer passes it through untouched (`EventObserver:56`, `CalculateDatesEvents:237`) — and it exposed an inconsistency worth knowing about: only `EventDeletedNotification` guards the value with `?? ''`; the other three call `trim()` on the null directly. All four reach the same address, so this is recorded rather than fixed.

`tests/Unit/Notifications/NotificationEnvFallbackTest.php` pinned all of it, including a real send that proved the hard failure was a failure and not a cosmetic one; every one of those cases is now inverted and asserts that removing the environment variable changes nothing. `tests/Feature/ConfigCacheSafetyTest.php` is the standing guard: it tokenizes `app/`, `routes/`, `database/` and `resources/views/` — compiling the Blade views first — and fails on any runtime `env()` call outside `config/`.

**Why this mattered right then.** `artisan optimize` had never completed on this codebase, because a duplicate route name made `route:cache` throw (v1-patch A7). The moment that was fixed, `config:cache` became reachable in practice, and with it every trap in the table above.

## The failed-job monitor is a notification path too

`spatie/laravel-failed-job-monitor` sends `Spatie\FailedJobMonitor\Notification` on every `JobFailed` event, over the `mail` channel (`config/failed-job-monitor.php`). Its notifiable is the package's own `Spatie\FailedJobMonitor\Notifiable`, not a `User`, and it routes to a single address taken straight from configuration.

**That route had the same shape of defect the table above describes, and TODO 36 fixed it.** `routeNotificationForMail()` is typed `: array` and returns `config('failed-job-monitor.mail.to')` untouched, so a **null** recipient is a `TypeError` rather than a missing address. The config used to read `env('MAIL_FROM_ADDRESS', 'email@example.com')`, and an `env()` default only applies when the **key is absent** — while `.env.example` ships `MAIL_FROM_ADDRESS=null`, which `env()` resolves to a real null. On an install that had not configured mail yet, the first failed queue job took down the thing whose entire job is to report failed queue jobs.

The line is now `env('MAIL_FROM_ADDRESS') ?: 'email@example.com'`. `?:` rather than `??` on purpose: `env()` returns `''` for an empty value, and `??` would pass that through into an invalid address further down. `tests/Feature/Mail/FailedJobMonitorRouteTest.php` holds all three parts — the vendor's `TypeError` as a constraint, `env()`'s handling of the literal `"null"` string, and what the config file produces for both values that used to break it.

## Notes

- All notifications currently use mail delivery; no database/broadcast channel implementations are present.
- Several class names contain legacy typos (`deletePersonalDataNotification`, `UserWillBeAnyonimizeAdminNotification`).
- No class in `app/` may carry a `Test` suffix — PHPUnit would treat it as a test class. Enforced by `tests/Unit/ApplicationNamingConventionTest.php`, added when `GroupPriorityMessageNotificationTest` (an accidental `make:notification` stub with no dispatch site) was deleted in roadmap TODO 15.
- Delivery suppression for opted-out users depends on `User::opted_out_of_notifications` keys matching notification class names.
