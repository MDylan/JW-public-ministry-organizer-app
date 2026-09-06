<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 32: the STRUCTURE of the schema, pinned.
 *
 * EncryptedColumnSchemaTest pins nine columns, because those nine are the
 * ones an attribute loss would destroy data in. This file pins the shape
 * around them - which tables exist, which foreign keys enforce which
 * referential action, and which unique indexes carry a correctness
 * guarantee.
 *
 * The reason it exists is that four more framework majors have to replay the
 * whole migration path on every fresh install, and a structural loss along
 * the way raises no error at all: a missing table only fails the first query
 * that needs it, and a missing foreign key or unique index fails nothing at
 * all until the data is already wrong. Nothing covered any of this before -
 * the two assertions that existed sat inside feature tests for the features
 * that happened to need them.
 *
 * The lists below are the assertion, the same shape as the route contract
 * fixture (TODO 01) and the encrypted cast list (TODO 13). Adding a table, a
 * foreign key or a unique index has to be a visible diff here.
 *
 * Measured from the kozter_testing schema, which is built from the migration
 * path itself - these are therefore facts about the repository, not figures
 * about any deployment's data.
 */
class SchemaStructureTest extends FeatureTestCase
{
    /**
     * Every table the migration path creates, Laravel's own included.
     */
    private const EXPECTED_TABLES = [
        'activity_log',
        'admin_newsletter_reads',
        'admin_newsletter_translations',
        'admin_newsletters',
        'day_stats',
        'event_service_reports',
        'events',
        'failed_jobs',
        'group_dates',
        'group_day_disabled_slots',
        'group_days',
        'group_future_changes',
        'group_literatures',
        'group_messages',
        'group_news',
        'group_news_files',
        'group_news_translations',
        'group_news_user_logs',
        'group_poster_reads',
        'group_posters',
        'group_survey_answers',
        'group_survey_statistics',
        'group_surveys',
        'group_user',
        'groups',
        'jobs',
        'log_histories',
        'migrations',
        'password_resets',
        'pending_user_emails',
        'settings',
        'static_page_translations',
        'static_pages',
        'statistics',
        'users',
        'weather_cities',
    ];

    /**
     * Constraint name => "table.column -> referenced.column ON DELETE rule".
     *
     * The delete rule is the half that carries behaviour and the half a
     * rewritten schema loses most easily: CASCADE and SET NULL both change
     * rows elsewhere, while the default NO ACTION merely refuses the delete.
     */
    private const EXPECTED_FOREIGN_KEYS = [
        'admin_newsletter_reads_admin_newsletter_id_foreign' => 'admin_newsletter_reads.admin_newsletter_id -> admin_newsletters.id ON DELETE CASCADE',
        'admin_newsletter_reads_user_id_foreign' => 'admin_newsletter_reads.user_id -> users.id ON DELETE CASCADE',
        'admin_newsletter_translations_admin_newsletter_id_foreign' => 'admin_newsletter_translations.admin_newsletter_id -> admin_newsletters.id ON DELETE CASCADE',
        'day_stats_group_id_foreign' => 'day_stats.group_id -> groups.id ON DELETE NO ACTION',
        'event_service_reports_event_id_foreign' => 'event_service_reports.event_id -> events.id ON DELETE CASCADE',
        'event_service_reports_group_literature_id_foreign' => 'event_service_reports.group_literature_id -> group_literatures.id ON DELETE CASCADE',
        'events_accepted_by_foreign' => 'events.accepted_by -> users.id ON DELETE CASCADE',
        'events_group_id_foreign' => 'events.group_id -> groups.id ON DELETE CASCADE',
        'events_user_id_foreign' => 'events.user_id -> users.id ON DELETE CASCADE',
        'group_dates_group_id_foreign' => 'group_dates.group_id -> groups.id ON DELETE CASCADE',
        'group_day_disabled_slots_group_id_foreign' => 'group_day_disabled_slots.group_id -> groups.id ON DELETE NO ACTION',
        'group_future_changes_group_id_foreign' => 'group_future_changes.group_id -> groups.id ON DELETE NO ACTION',
        'group_future_changes_user_id_foreign' => 'group_future_changes.user_id -> users.id ON DELETE NO ACTION',
        'group_messages_group_id_foreign' => 'group_messages.group_id -> groups.id ON DELETE CASCADE',
        'group_messages_user_id_foreign' => 'group_messages.user_id -> users.id ON DELETE CASCADE',
        'group_news_files_group_new_id_foreign' => 'group_news_files.group_new_id -> group_news.id ON DELETE CASCADE',
        'group_news_group_id_foreign' => 'group_news.group_id -> groups.id ON DELETE NO ACTION',
        'group_news_translations_group_news_id_foreign' => 'group_news_translations.group_news_id -> group_news.id ON DELETE CASCADE',
        'group_news_user_logs_group_id_foreign' => 'group_news_user_logs.group_id -> groups.id ON DELETE NO ACTION',
        'group_poster_reads_poster_id_foreign' => 'group_poster_reads.poster_id -> group_posters.id ON DELETE CASCADE',
        'group_poster_reads_user_id_foreign' => 'group_poster_reads.user_id -> users.id ON DELETE CASCADE',
        'group_posters_group_id_foreign' => 'group_posters.group_id -> groups.id ON DELETE NO ACTION',
        'group_survey_answers_group_survey_id_foreign' => 'group_survey_answers.group_survey_id -> group_surveys.id ON DELETE NO ACTION',
        'group_survey_statistics_group_survey_answer_id_foreign' => 'group_survey_statistics.group_survey_answer_id -> group_survey_answers.id ON DELETE NO ACTION',
        'group_survey_statistics_user_id_foreign' => 'group_survey_statistics.user_id -> users.id ON DELETE NO ACTION',
        'group_surveys_group_id_foreign' => 'group_surveys.group_id -> groups.id ON DELETE NO ACTION',
        'groups_city_id_foreign' => 'groups.city_id -> weather_cities.id ON DELETE SET NULL',
        'log_histories_group_id_foreign' => 'log_histories.group_id -> groups.id ON DELETE CASCADE',
        'static_page_translations_static_page_id_foreign' => 'static_page_translations.static_page_id -> static_pages.id ON DELETE CASCADE',
        'static_pages_user_id_foreign' => 'static_pages.user_id -> users.id ON DELETE NO ACTION',
    ];

    /**
     * Every unique index that is not a PRIMARY KEY, as
     * "table.index_name" => "column,column" in index order.
     *
     * These are the constraints the application leans on instead of checking
     * in PHP; pending_user_emails_user_unique is the one
     * PendingEmailIntegrityTest also exercises behaviourally.
     */
    private const EXPECTED_UNIQUE_INDEXES = [
        'admin_newsletter_reads.admin_newsletter_reads_user_id_admin_newsletter_id_unique' => 'user_id,admin_newsletter_id',
        'admin_newsletter_translations.admin_newsletter_translations_admin_newsletter_id_locale_unique' => 'admin_newsletter_id,locale',
        'failed_jobs.failed_jobs_uuid_unique' => 'uuid',
        'group_dates.group_dates_group_id_date_unique' => 'group_id,date',
        'group_future_changes.group_future_changes_group_id_unique' => 'group_id',
        'group_news_translations.group_news_translations_group_news_id_locale_unique' => 'group_news_id,locale',
        'group_poster_reads.group_poster_reads_user_id_poster_id_unique' => 'user_id,poster_id',
        // The name says group_survey_answer_id while the column is user_id.
        // That mismatch is inherited, not a transcription error here, and the
        // baseline has to reproduce it rather than tidy it up - renaming an
        // index inside a squash would be a schema change hiding in a refactor.
        'group_survey_statistics.group_survey_answer_id' => 'user_id',
        'pending_user_emails.pending_user_emails_user_unique' => 'user_type,user_id',
        'static_page_translations.static_page_translations_static_page_id_locale_unique' => 'static_page_id,locale',
        'static_pages.static_pages_slug_unique' => 'slug',
        'users.users_email_unique' => 'email',
        'weather_cities.weather_cities_city_country_unique' => 'city,country',
    ];

    /** @return list<string> */
    private function tableNames(): array
    {
        return array_map(
            static fn ($row) => $row->TABLE_NAME,
            DB::select(
                'select TABLE_NAME from information_schema.TABLES
                 where TABLE_SCHEMA = ? order by TABLE_NAME',
                [DB::getDatabaseName()]
            )
        );
    }

    /** @return list<string> */
    private function expectedTablesSorted(): array
    {
        $expected = self::EXPECTED_TABLES;
        sort($expected);

        return $expected;
    }

    public function test_the_schema_holds_exactly_the_expected_tables(): void
    {
        $this->assertSame(
            $this->expectedTablesSorted(),
            $this->tableNames(),
            'A táblakészlet eltér a rögzítettől. Ha ez szándékos, írd át az EXPECTED_TABLES-t.'
        );
    }

    public function test_every_foreign_key_keeps_its_target_and_delete_rule(): void
    {
        $rows = DB::select(
            'select k.CONSTRAINT_NAME, k.TABLE_NAME, k.COLUMN_NAME,
                    k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE
             from information_schema.KEY_COLUMN_USAGE k
             join information_schema.REFERENTIAL_CONSTRAINTS r
               on r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
              and r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
             where k.TABLE_SCHEMA = ?
               and k.REFERENCED_TABLE_NAME is not null
             order by k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
            [DB::getDatabaseName()]
        );

        $actual = [];
        foreach ($rows as $row) {
            $actual[$row->CONSTRAINT_NAME] = $row->TABLE_NAME.'.'.$row->COLUMN_NAME
                .' -> '.$row->REFERENCED_TABLE_NAME.'.'.$row->REFERENCED_COLUMN_NAME
                .' ON DELETE '.$row->DELETE_RULE;
        }

        $expected = self::EXPECTED_FOREIGN_KEYS;
        ksort($expected);
        ksort($actual);

        $this->assertSame(
            $expected,
            $actual,
            'Az idegen kulcsok készlete vagy törlési szabálya eltér a rögzítettől.'
        );
    }

    public function test_every_unique_index_keeps_its_name_and_column_order(): void
    {
        // The column ORDER is part of the assertion rather than decoration: a
        // composite unique index constrains the same set either way, but the
        // leading column decides what the index can also be read on, so a
        // swapped pair is a silent query-plan change.
        $rows = DB::select(
            'select TABLE_NAME, INDEX_NAME,
                    GROUP_CONCAT(COLUMN_NAME order by SEQ_IN_INDEX) as COLS
             from information_schema.STATISTICS
             where TABLE_SCHEMA = ? and NON_UNIQUE = 0 and INDEX_NAME <> ?
             group by TABLE_NAME, INDEX_NAME',
            [DB::getDatabaseName(), 'PRIMARY']
        );

        $actual = [];
        foreach ($rows as $row) {
            $actual[$row->TABLE_NAME.'.'.$row->INDEX_NAME] = $row->COLS;
        }

        $expected = self::EXPECTED_UNIQUE_INDEXES;
        ksort($expected);
        ksort($actual);

        $this->assertSame(
            $expected,
            $actual,
            'Az egyedi indexek készlete, neve vagy oszlopsorrendje eltér a rögzítettől.'
        );
    }

    public function test_only_the_known_table_lacks_a_primary_key(): void
    {
        // A table the baseline creates without its PRIMARY KEY still accepts
        // every write the suite makes, so nothing else here would catch it.
        //
        // Measured rather than assumed, and the measurement corrected the
        // assertion: password_resets has no primary key. That is Laravel's
        // own stock migration shape - an indexed email, a token and a
        // nullable created_at, nothing more - not a defect of this project,
        // and the baseline has to reproduce it as it stands. Pinning the
        // exception is worth more than dropping the test: a SECOND table
        // arriving without a primary key would be an error.
        $rows = DB::select(
            'select distinct TABLE_NAME from information_schema.STATISTICS
             where TABLE_SCHEMA = ? and INDEX_NAME = ?',
            [DB::getDatabaseName(), 'PRIMARY']
        );

        $withPrimaryKey = array_map(static fn ($row) => $row->TABLE_NAME, $rows);

        $this->assertSame(
            ['password_resets'],
            array_values(array_diff($this->expectedTablesSorted(), $withPrimaryKey)),
            'Az elsődleges kulcs nélküli táblák köre megváltozott.'
        );
    }

    public function test_the_orphaned_translation_tables_are_gone(): void
    {
        // The joedixon package created these unconditionally through
        // loadMigrationsFrom and nothing ever read them; TODO 33.3 dropped
        // them. The drop migration has never shipped, so this assertion is
        // also what proves it still runs - and it is the reason the two names
        // remain in the migrations table of every deployed host with no file
        // behind them.
        $tables = $this->tableNames();

        $this->assertNotContains('languages', $tables);
        $this->assertNotContains('translations', $tables);
    }
}
