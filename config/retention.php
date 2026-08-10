<?php

/*
|--------------------------------------------------------------------------
| Data retention
|--------------------------------------------------------------------------
|
| How long old data is kept. Three data sets have three separate switches,
| because three different reasons drive them:
|
|   - events (+ the service reports that cascade from them): GDPR.
|     The switch is `gdpr.enabled`, the window is `events_months` here.
|   - group data (day_stats, group_dates): SIZE. They carry no personal
|     data, so they don't belong to the GDPR switch but to an admin UI
|     setting (`settings.group_data_retention`), whose allowed values are
|     listed by `group_data_options`.
|   - activity log: the config/activitylog.php `delete_records_older_than_days`
|     key (90 days), via the Spatie `activitylog:clean` command.
|
| This file DELIBERATELY has no env() call. The retention window is a
| policy decision, not a per-install setting, so the file is inherently
| `config:cache`-safe - see the explanation in config/security.php.
|
| The retention limits must never be recalculated by hand anywhere: the
| single source of truth is App\Support\Retention\RetentionWindow, which
| both the commands and the UI limits read.
|
*/

return [

    /*
    | Events older than this many months are deleted. The delete is
    | PERMANENT, and cascades through the foreign key to remove the
    | event's service reports too (event_service_reports, ON DELETE CASCADE).
    */
    'events_months' => 13,

    /*
    | The allowed values, in months, for the `group_data_retention` admin
    | setting; '0' is the disabled state. This list serves both as the
    | source for the UI selector and as the RetentionWindow whitelist:
    | a value can end up in the settings table without validation, so
    | CASTING IS FORBIDDEN when reading it back - an (int) 'x' would yield
    | zero, and a zero-month window would wipe the entire table.
    */
    'group_data_options' => ['0', '12', '24'],

];
