# Database schema and migrations

## The migration history is kept, deliberately

`database/migrations/` holds the full history. Roadmap TODO 32 considered
squashing it into a single baseline, built one, verified it produced a
byte-identical schema - and then **reverted it**, because the history is what
makes an upgrade of an existing installation safe:

- The self-updater unpacks an archive and runs `migrate --force`. An
  installation that is several releases behind gets every migration it has not
  run yet, from its own copy of the files, with no coordination needed.
- A baseline cannot do that. It has to carry a guard that skips it on any
  existing installation, so anything folded into it never reaches a host that
  was behind - and the schema freezes silently. Preventing that would have made
  the `previous_version` chain in `laraupdater.json` load-bearing for schema
  correctness rather than merely for the major-version ceiling.

The cost of keeping the history is that every migration has to keep running on
each new framework major. That is a real cost, and the section below is the one
place where it has already come due.

## The `->change()` trap, and how it is closed

Eight columns reached their current shape through a `->change()` call, in seven
migrations. Under Laravel 8-10 - which since TODO 39 means the version actually
running - those go through `doctrine/dbal`, which reads the
column's current definition and alters only what the migration redeclares.
**Laravel 11 makes `change()` native, and the native one drops every attribute
that is not redeclared** - nullability, default, charset, collation.

Measured, six of the eight redeclare enough to survive. Two do not:

| Column | Declared | Laravel default | Actual | Diverges |
|---|---|---|---|---|
| `events.comment` | `text('comment')` | NOT NULL | **NULL** | yes |
| `group_user.note` | `text('note')` | NOT NULL | **NULL** | yes |

Both carry the `encrypted` cast, and `null` bypasses that cast in both
directions - so a nullability flip there is a write failure, not a cosmetic
difference.

**Who it would have bitten, and when.** Nobody on an existing installation:
those migrations have run and never run again. Only a **fresh install after the
Laravel 11 hop**, which is what the web installer does on customer hosting. Old
and new installations would then diverge permanently, and the difference would
not surface at migration time - it would surface months later, the first time
somebody saved an event with no comment.

`2026_09_06_120000_pin_the_changed_column_definitions` closes it. It **restates**
the final definition of all eight columns as raw `ALTER TABLE`, so the outcome
does not depend on what any version of `change()` preserves. It compares against
`information_schema` first and issues a statement only for a column that has
actually drifted: a `MODIFY` on a TEXT column can rebuild the table, and on a
deployed host this runs inside the updater's web request against tables that are
not small. On every existing installation it therefore issues nothing at all.

`tests/Feature/Database/ChangedColumnDefinitionsTest.php` covers it, including a
census that fails if a new `->change()` call appears on a column the migration
does not pin.

## Where migrations actually run

| Path | Call |
|---|---|
| Fresh install | `Setup\DatabaseController::migrateDatabase()` - `migrate:fresh` on a runtime-registered `setup` connection, from a web request |
| Self-update | `LaraUpdaterController::update()` - `migrate --force`, after the archive is unpacked and the `upgrade.php` hook has run |
| Admin UI | `Admin\Settings::run('migrate')` - `migrate --force` |
| Test suite | `RefreshDatabase`, i.e. `migrate:fresh` on `kozter_testing` |

`Setup\DatabaseController`'s success branch is deliberately **not** covered by
the suite - a mistaken test there would wipe a database - so anything that
changes what `migrate:fresh` does has to be verified against a scratch database
by hand.

## Where the schema is pinned

Nothing asserts the schema from the migration source; it is all read back from
the database:

- `tests/Feature/Database/SchemaStructureTest.php` - the exact table set, every
  foreign key with its delete rule, every non-primary unique index with its
  column order, the one table that legitimately has no primary key
  (`password_resets`, which is Laravel's own stock shape), and the absence of
  the tables the translation package left behind. A structural loss raises no
  error on its own: a missing foreign key or unique index fails nothing until
  the data is already wrong.
- `tests/Feature/Models/EncryptedColumnSchemaTest.php` - type, nullability,
  length, charset **and collation** for the nine encrypted columns. Collation
  matters beyond tidiness: it is in the same attribute group the native
  `change()` drops, and a definition that omits `COLLATE` inherits the table
  default - which, if that ever fell back to the server default, is
  `utf8mb4_0900_ai_ci` on MySQL 8 rather than the `utf8mb4_unicode_ci`
  `config/database.php` declares.
- `tests/Feature/Models/EncryptedAttributeTest.php` - the behaviour those
  columns exist for, including that a raw query-builder write makes an
  encrypted column unreadable.

Two smaller assertions live with their own features rather than here:
`WeatherKnownGapsTest` checks the `groups.city_id` foreign key behaviourally,
and `PendingEmailIntegrityTest` checks that the unique index on
`pending_user_emails` actually rejects a second row.

## Two things measured while all this was investigated

**DDL causes an implicit COMMIT in MySQL.** A single `ALTER TABLE` inside a test
ends the transaction `RefreshDatabase` opened, the rollback afterwards does
nothing, and the fixtures survive into the next test - which then dies on a
duplicate e-mail address. Any test that has to perform DDL must do it on its own
database; `ChangedColumnDefinitionsTest` shows the pattern.

**`SHOW CREATE TABLE` is not idempotent on the first round trip.** A column that
inherits its collation prints only `COLLATE utf8mb4_unicode_ci`, because that is
not utf8mb4's default collation on MySQL 8. Feeding that text back in makes the
collation explicit, so the next dump also prints `CHARACTER SET utf8mb4`. The
column is unchanged - `information_schema` reports the same charset and
collation either way - and a second round trip is a fixed point. Any tool that
diffs one schema against another through their DDL text has to normalise that
clause away first, or every text-bearing table reads as different.
