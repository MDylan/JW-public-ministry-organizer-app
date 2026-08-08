<?php

namespace Tests\Feature\Factories;

use App\Models\AdminNewsletter;
use App\Models\AdminNewsletterRead;
use App\Models\AdminNewsletterTranslation;
use App\Models\DayStat;
use App\Models\Event;
use App\Models\EventServiceReport;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\GroupDay;
use App\Models\GroupDayDisabledSlots;
use App\Models\GroupFutureChange;
use App\Models\GroupLiterature;
use App\Models\GroupMessage;
use App\Models\GroupNews;
use App\Models\GroupNewsFile;
use App\Models\GroupNewsTranslation;
use App\Models\GroupNewsUserLogs;
use App\Models\GroupPosterRead;
use App\Models\GroupPosters;
use App\Models\GroupSurvey;
use App\Models\GroupSurveyAnswer;
use App\Models\GroupSurveyStatistics;
use App\Models\GroupUser;
use App\Models\LogHistory;
use App\Models\Settings;
use App\Models\StaticPage;
use App\Models\StaticPageTranslation;
use App\Models\Statistics;
use App\Models\User;
use App\Models\WeatherCity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Feature\FeatureTestCase;

/**
 * Guards TODO 04: every model must have a working factory.
 *
 * A factory that cannot persist its own default definition is worse than no
 * factory at all, because it fails deep inside an unrelated test. This suite
 * exercises each one directly so a schema drift surfaces here first.
 */
class ModelFactoryTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Több observer (GroupLiteratureObserver, GroupNewsObserver,
        // GroupNewsTranslationObserver, ...) feltétel nélkül auth()->user()->id-t
        // olvas, ezért bejelentkezett kontextus nélkül a modell mentése elszáll.
        // Ez tükrözi az alkalmazás valós működését: minden írás bejelentkezett
        // felhasználótól érkezik. Lásd: roadmap TODO 10.
        $this->actingAs($this->createUser(['email' => 'factory-actor@example.test']));
    }

    /**
     * Every model that ships a factory, so the list itself is an assertion:
     * adding a model without a factory should make this test fail.
     */
    public static function factoryBackedModels(): array
    {
        return [
            // Korábban is létező factory-k
            'User' => [User::class],
            'Group' => [Group::class],
            'GroupUser' => [GroupUser::class],
            'Event' => [Event::class],
            'GroupDate' => [GroupDate::class],
            'GroupDay' => [GroupDay::class],
            'StaticPage' => [StaticPage::class],

            // TODO 04-ben hozzáadott factory-k
            'AdminNewsletter' => [AdminNewsletter::class],
            'AdminNewsletterRead' => [AdminNewsletterRead::class],
            'AdminNewsletterTranslation' => [AdminNewsletterTranslation::class],
            'DayStat' => [DayStat::class],
            'EventServiceReport' => [EventServiceReport::class],
            'GroupDayDisabledSlots' => [GroupDayDisabledSlots::class],
            'GroupFutureChange' => [GroupFutureChange::class],
            'GroupLiterature' => [GroupLiterature::class],
            'GroupMessage' => [GroupMessage::class],
            'GroupNews' => [GroupNews::class],
            'GroupNewsFile' => [GroupNewsFile::class],
            'GroupNewsTranslation' => [GroupNewsTranslation::class],
            'GroupNewsUserLogs' => [GroupNewsUserLogs::class],
            'GroupPosterRead' => [GroupPosterRead::class],
            'GroupPosters' => [GroupPosters::class],
            'GroupSurvey' => [GroupSurvey::class],
            'GroupSurveyAnswer' => [GroupSurveyAnswer::class],
            'GroupSurveyStatistics' => [GroupSurveyStatistics::class],
            'LogHistory' => [LogHistory::class],
            'Settings' => [Settings::class],
            'StaticPageTranslation' => [StaticPageTranslation::class],
            'Statistics' => [Statistics::class],
            'WeatherCity' => [WeatherCity::class],
        ];
    }

    /**
     * @dataProvider factoryBackedModels
     */
    public function test_factory_persists_its_default_definition(string $modelClass): void
    {
        /** @var Model $model */
        $model = $modelClass::factory()->create();

        $this->assertTrue($model->exists, $modelClass.' factory did not persist the model.');
        $this->assertNotNull($model->getKey(), $modelClass.' factory produced a model without a key.');
        $this->assertDatabaseHas($model->getTable(), [
            $model->getKeyName() => $model->getKey(),
        ]);
    }

    /**
     * @dataProvider factoryBackedModels
     */
    public function test_factory_can_create_multiple_records(string $modelClass): void
    {
        $before = $modelClass::count();

        $modelClass::factory()->count(2)->create();

        // Nem pontos egyezést várunk: a fordítás-factory-k szülője (GroupNews,
        // AdminNewsletter) maga is ír egy sort a fordítástáblába, így egy
        // kétszeres create() négy sort eredményez. A lényeg, hogy a factory
        // ismételhető legyen unique-ütközés nélkül.
        $this->assertGreaterThanOrEqual(
            $before + 2,
            $modelClass::count(),
            $modelClass.' factory cannot produce multiple records (likely a unique-column clash).'
        );
    }

    public function test_translation_factories_can_add_a_second_locale_to_an_existing_parent(): void
    {
        // Ez a fordítás-factory-k valós használati mintája: meglévő szülőhöz
        // adunk egy további nyelvet.
        $news = GroupNews::factory()->create();
        $newsletter = AdminNewsletter::factory()->create();

        GroupNewsTranslation::factory()->forNews($news)->locale('de')->create();
        AdminNewsletterTranslation::factory()
            ->state(['admin_newsletter_id' => $newsletter->id])
            ->locale('de')
            ->create();

        $this->assertSame(2, GroupNewsTranslation::where('group_news_id', $news->id)->count());
        $this->assertSame(2, AdminNewsletterTranslation::where('admin_newsletter_id', $newsletter->id)->count());
        $this->assertNotEmpty($news->fresh()->translate('de')->title);
    }

    // --- Célzott ellenőrzések, ahol a factory nem triviális ---

    public function test_translatable_factories_write_the_translation_row(): void
    {
        $locale = config('app.locale', 'hu');

        $news = GroupNews::factory()->create();
        $newsletter = AdminNewsletter::factory()->create();

        $this->assertDatabaseHas('group_news_translations', [
            'group_news_id' => $news->id,
            'locale' => $locale,
        ]);
        $this->assertNotEmpty($news->fresh()->translate($locale)->title);

        $this->assertDatabaseHas('admin_newsletter_translations', [
            'admin_newsletter_id' => $newsletter->id,
            'locale' => $locale,
        ]);
        $this->assertNotEmpty($newsletter->fresh()->translate($locale)->subject);
    }

    public function test_encrypted_attribute_factories_produce_decryptable_values(): void
    {
        // Ez a fájl a GYÁRAKRÓL szól, nem a titkosításról: itt csak annyit
        // kell tudni, hogy a két érintett gyár nyílt szöveget vár és a cast
        // lefut rá. A titkosítás teljes viselkedését - mind a 9 oszlopon,
        // nullal, üres stringgel, hosszal, rossz kulccsal és a castot
        // megkerülő írással - a TODO 13 fájljai mérik:
        // tests/Feature/Models/EncryptedAttributeTest.php és
        // tests/Feature/Models/EncryptedColumnSchemaTest.php.
        $poster = GroupPosters::factory()->create(['info' => 'Titkos hirdetmény']);
        $message = GroupMessage::factory()->create(['message' => 'Titkos üzenet']);

        $this->assertSame('Titkos hirdetmény', $poster->fresh()->info);
        $this->assertSame('Titkos üzenet', $message->fresh()->message);
    }

    public function test_array_cast_factories_store_and_read_back_arrays(): void
    {
        $change = GroupFutureChange::factory()->create();
        $weather = WeatherCity::factory()->withWeatherData()->create();

        $this->assertIsArray($change->fresh()->group);
        $this->assertIsArray($change->fresh()->days);
        $this->assertIsArray($change->fresh()->disabled_slots);

        // A WeatherCityFactory a v1-patch C csomagja óta az OpenWeather VALÓDI
        // válaszalakját írja. Korábban egy kitalált, lapos szerkezet állt itt
        // (['temp' => 21.5]), amit a termelés nem tudott előállítani: a mentés
        // kétszer kódolt, tehát élesben sztring volt az oszlopban.
        $this->assertIsArray($weather->fresh()->current_weather);
        $this->assertSame(21.5, $weather->fresh()->current_weather['main']['temp']);

        $this->assertIsArray($weather->fresh()->forecast_weather);
        $this->assertArrayHasKey('list', $weather->fresh()->forecast_weather);
        $this->assertArrayHasKey('dt_txt', $weather->fresh()->forecast_weather['list'][0]);
    }

    public function test_log_history_factory_binds_a_morph_target(): void
    {
        $literature = GroupLiterature::factory()->create();

        $log = LogHistory::factory()->forModel($literature)->create();

        $this->assertSame(GroupLiterature::class, $log->model_type);
        $this->assertSame($literature->id, (int) $log->model_id);
        $this->assertTrue($log->model->is($literature));
    }
}
