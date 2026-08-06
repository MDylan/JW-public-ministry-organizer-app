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

### Two kinds of coverage

`tests/Unit/Notifications/NotificationRegressionTest.php` covers the **mail contract** of every class: whether it queues, whether `mail` is among its channels, and whether `toMail()` builds a `MailMessage`. That says nothing about whether the notification is ever *sent*. Its data provider is itself an assertion — `test_every_notification_class_has_a_mail_contract` reads this directory and fails if a class is added without a contract row (roadmap TODO 15).

The "Covered by" column below records the **dispatch trigger** test — the one that would fail if a notification silently stopped going out. Settled in roadmap TODO 11. **Every notification now has both kinds of coverage, with no exceptions**: the last gap, `TestNotification`'s setup-flow dispatch site, was closed by TODO 12, and the one class that had no dispatch site at all was deleted in TODO 15.

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
| `UserEmailChangedNotification` | Yes | `Actions\Fortify\UpdateUserProfileInformation` | `NotificationTriggerRegressionTest` | Alerts old email address about pending new email. |
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

## Environment dependencies

Six notifications read `env()` at **runtime**, outside a config file. When configuration is cached (`php artisan config:cache`) Laravel never loads the `.env` file, so those calls return `null`. Two severity classes, and the difference matters:

| Notification | Call | Effect once `env()` returns null |
|---|---|---|
| `EventCreatedNotification:68` | `->replyTo(env('MAIL_FROM_ADDRESS'))` | **Sending fails** |
| `EventDeletedNotification:66` | `->replyTo(env('MAIL_FROM_ADDRESS'))` | **Sending fails** |
| `EventStatusChangedNotification:71` | `->replyTo(env('MAIL_FROM_ADDRESS'))` | **Sending fails** |
| `EventUpdatedNotification:75` | `->replyTo(env('MAIL_FROM_ADDRESS'))` | **Sending fails** |
| `UserRoleIsGroupCreatorNotification:45` | `->bcc(env('MAIL_FROM_ADDRESS'))` | **Sending fails** |
| `UserWillBeAnonymizeNotification:62` | `env('APP_NAME')` in a translation placeholder | Mail still goes out, with an empty app name |

The `replyTo`/`bcc` fallback only applies when the group's own `replyTo` is blank, so the failure is data-dependent rather than universal. The measured failure today is `Swift_RfcComplianceException: Address in mailbox given [] does not comply with RFC 2822, 3.6.2.`; after Phase 5 swaps SwiftMailer for Symfony Mailer the same condition surfaces as `Symfony\Component\Mime\Exception\RfcComplianceException`.

`tests/Unit/Notifications/NotificationEnvFallbackTest.php` pins all of it, including a real send that proves the hard failure is a failure and not a cosmetic one. The fix in Phase 4 is one line per site — `config('mail.from.address')` and `config('app.name')`, which read the same values through the cacheable path.

## Notes

- All notifications currently use mail delivery; no database/broadcast channel implementations are present.
- Several class names contain legacy typos (`deletePersonalDataNotification`, `UserWillBeAnyonimizeAdminNotification`).
- No class in `app/` may carry a `Test` suffix — PHPUnit would treat it as a test class. Enforced by `tests/Unit/ApplicationNamingConventionTest.php`, added when `GroupPriorityMessageNotificationTest` (an accidental `make:notification` stub with no dispatch site) was deleted in roadmap TODO 15.
- Delivery suppression for opted-out users depends on `User::opted_out_of_notifications` keys matching notification class names.
