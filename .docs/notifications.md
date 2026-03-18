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

## Notification Catalog

## Event Domain

| Notification | Queued | Triggered From | Purpose |
|---|---|---|---|
| `EventCreatedNotification` | Yes | `EventObserver::created` | Informs assigned user when someone else creates their event. |
| `EventUpdatedNotification` | Yes | `EventObserver::updated`, `CalculateDatesEvents::generate` | Informs users about schedule/time changes. |
| `EventStatusChangedNotification` | Yes | `EventObserver::updated` | Informs users when event status changes (accept/reject flow). |
| `EventDeletedNotification` | Yes | `EventObserver::deleted`, `CalculateDatesEvents`, `GroupDayDeletedProcess`, `UserLogoutFromGroupProcess` | Informs users that an event was removed. |
| `EventDeletedAdminsNotification` | Yes | `EventObserver::deleted` (accepted event path) | Notifies group editors/admins about accepted-event deletions. |

## Group Domain

| Notification | Queued | Triggered From | Purpose |
|---|---|---|---|
| `GroupUserAddedNotification` | Yes | `GroupUserMoves::attach`, `Livewire\Groups\ListUsers` | Notifies user they were attached to a group. |
| `GroupUserLogoutNotification` | Yes | `GroupUserMoves::detach` | Notifies user they were removed from a group. |
| `GroupPriorityMessageNotification` | Yes | `Livewire\Groups\Messages` | Sends high-priority group board messages. |
| `GroupParentGroupAttachedNotification` | Yes | `Livewire\Groups\ListUsers` | Notifies admins when parent-child group link is created. |
| `GroupParentGroupDetachedNotification` | Yes | `Livewire\Groups\ListUsers` | Notifies admins when parent-child group link is removed. |
| `Newsletter` | Yes | `Console\Kernel` scheduled task | Sends scheduled admin newsletters to segmented audiences. |

## User / Account Domain

| Notification | Queued | Triggered From | Purpose |
|---|---|---|---|
| `UserRegisteredNotification` | Yes | `Actions\Fortify\CreateNewUser` | Registration email with verification link. |
| `FinishRegistration` | Yes | `Livewire\Groups\ListUsers` | Invitation-style email to complete account registration. |
| `FinishRegistrationSuccessNotification` | Yes | `Http\Controllers\FinishRegistration` | Confirms successful finish-registration flow. |
| `UserEmailChangedNotification` | Yes | `Actions\Fortify\UpdateUserProfileInformation` | Alerts old email address about pending new email. |
| `LoginData` | Yes | `Livewire\Groups\ListUsers` | Sends login details/entry data to created users. |
| `UserProfileChangedNotification` | Yes | `Livewire\Groups\ListUsers` | Notifies user profile was modified by admin/editor. |
| `UserProfileRenewalNotification` | Yes | `Livewire\Groups\ListUsers` | Asks user to renew/update profile data. |
| `UserProfileRenewalAdminNotification` | Yes | `Livewire\Groups\ListUsers` | Notifies group editors that renewal request was initiated. |
| `deletePersonalDataNotification` | Yes | `Http\Controllers\deletePersonalDataController` | Sends signed confirmation link before personal-data deletion. |

## Role / System Domain

| Notification | Queued | Triggered From | Purpose |
|---|---|---|---|
| `NewAdminNotification` | Yes | `UserObserver::adminAdded` | Alerts existing admins about a new main admin. |
| `UserRoleIsGroupCreatorNotification` | Yes | `UserObserver::updated` | Informs user when promoted to group creator role. |
| `UserWillBeAnonymizeNotification` | Yes | `Console\Kernel` scheduled GDPR task | Warns users before anonymization deadline. |
| `UserWillBeAnyonimizeAdminNotification` | Yes | `Console\Kernel` scheduled GDPR task | Warns group admins about users nearing anonymization. |

## Utility / Testing

| Notification | Queued | Triggered From | Purpose |
|---|---|---|---|
| `TestNotification` | No | `Setup\MailController`, `Livewire\Admin\Settings` | SMTP/mail configuration test notification. |
| `GroupPriorityMessageNotificationTest` | No | Not referenced in app flow | Test-only message notification class (currently unused). |

## Notes

- All notifications currently use mail delivery; no database/broadcast channel implementations are present.
- Several class names contain legacy typos (`deletePersonalDataNotification`, `UserWillBeAnyonimizeAdminNotification`).
- Delivery suppression for opted-out users depends on `User::opted_out_of_notifications` keys matching notification class names.
