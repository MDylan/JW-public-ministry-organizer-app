<?php

namespace Tests\Feature\Avatar;

use App\Http\Livewire\Groups\Messages;
use App\Models\GroupMessage;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Image;
use Laravolt\Avatar\Facade as Avatar;
use Livewire\Livewire;
use Psr\Http\Message\StreamInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 22.1: az avatar-generálás karakterizációs készlete.
 *
 * MIÉRT LÉTEZIK
 *
 * A TODO 22 azt volt hivatott igazolni, hogy a maradék függőségeken elég
 * verziót léptetni. Egyetlen csomagra ez bizonyíthatóan nem igaz: a
 * `laravolt/avatar` telepített 4.1.7-e az `illuminate/support ^9.0`-nál
 * elfogy, tehát MÁR A PHASE 5-ÖN (Laravel 10) elhasal a resolution - és a
 * `composer.json` `^4.1` kényszere egyetlen L10+ kiadást sem enged be. Minden
 * L12/L13-képes vonal (6.1.2 és fölötte) `intervention/image ^3.4`-et vagy
 * `^4.0`-t kér, ahol a lenti mechanizmus egy darabja megszűnik létezni.
 *
 * A csomagnak EGYETLEN hívási helye van az egész projektben
 * (app/Http/Livewire/Groups/Messages.php:174-176), és a TODO 22 előtt nulla
 * teszt gyakorolta. Ez a fájl azt rögzíti, ami MA történik, hogy a Phase 5-ös
 * migráció (TODO 39.1) diffje reviewálható legyen.
 *
 * A MÉRT TÉNY, AMI A TRIPWIRE-T ELDÖNTI
 *
 * Az Intervention Image 2-ben a `stream()` NEM valódi metódus: az Image
 * osztály docblockjának `@method` sora hirdeti (Image.php:53), és a `__call()`
 * (Image.php:106) dobja tovább a Commands\StreamCommand-nak. A v4-es
 * Image.php-ban se `stream()`, se `__call()`, se a Commands namespace nincs -
 * ott `encode(EncoderInterface)` és `encodeUsingFormat(Format)` van helyette.
 * A tripwire-szekció pontosan ezt a különbséget rögzíti, mindkét irányban.
 *
 * SZEMETELÉS
 *
 * Az integrációs tesztek a VALÓDI `web` diszkre írnak, mert csak az bizonyítja,
 * hova kerül a fájl. Ez nem új károsítás: a `public/avatars/*` gitignore-olt
 * (.gitignore:5). Amit a teszt létrehoz, azt a tearDown() eltakarítja.
 */
class AvatarGenerationTest extends FeatureTestCase
{
    /** A teszt által létrehozott avatar-fájlok, a `web` diszkhez relatívan. */
    private array $written = [];

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            if (Storage::disk('web')->exists($path)) {
                Storage::disk('web')->delete($path);
            }
        }

        $this->written = [];

        parent::tearDown();
    }

    /** A PNG-signatúra: 89 50 4E 47 0D 0A 1A 0A. */
    private function pngSignature(): string
    {
        return chr(0x89).'PNG'.chr(0x0D).chr(0x0A).chr(0x1A).chr(0x0A);
    }

    // =========================================================================
    // A mechanizmus, ahogy ma működik
    // =========================================================================

    public function test_the_facade_produces_an_intervention_image_and_a_png_stream(): void
    {
        $image = Avatar::create('Test User')->getImageObject();

        $this->assertInstanceOf(Image::class, $image);

        $stream = $image->stream('png');

        $this->assertInstanceOf(StreamInterface::class, $stream);
        $this->assertSame($this->pngSignature(), substr((string) $stream, 0, 8));
    }

    public function test_the_generated_image_carries_the_configured_dimensions(): void
    {
        $bytes = (string) Avatar::create('Test User')->getImageObject()->stream('png');

        [$width, $height] = getimagesizefromstring($bytes);

        $this->assertSame(100, config('laravolt.avatar.width'));
        $this->assertSame(100, config('laravolt.avatar.height'));
        $this->assertSame(100, $width);
        $this->assertSame(100, $height);
    }

    public function test_the_configured_driver_is_gd_and_it_is_available(): void
    {
        // A 6.x-re váltás az Intervention driver-kezelését is átírja, ezért a
        // kiindulási állapot rögzítve: GD, nem Imagick.
        $this->assertSame('gd', config('laravolt.avatar.driver'));
        $this->assertTrue(extension_loaded('gd'));
    }

    // =========================================================================
    // Az integráció: a Groups\Messages komponens
    // =========================================================================

    public function test_rendering_the_message_board_writes_one_png_per_author_to_the_web_disk(): void
    {
        [$user, $group] = $this->boardWithOneMessage();

        $path = $this->avatarPathFor($user->id);

        Storage::disk('web')->delete($path);
        $this->assertFalse(Storage::disk('web')->exists($path));

        Livewire::actingAs($user)->test(Messages::class, ['group' => $group]);

        $this->assertTrue(Storage::disk('web')->exists($path));
        $this->assertSame(
            $this->pngSignature(),
            substr(Storage::disk('web')->get($path), 0, 8)
        );
    }

    public function test_the_avatar_is_generated_once_and_never_overwritten(): void
    {
        [$user, $group] = $this->boardWithOneMessage();

        $path = $this->avatarPathFor($user->id);

        Livewire::actingAs($user)->test(Messages::class, ['group' => $group]);
        $this->assertTrue(Storage::disk('web')->exists($path));

        // A Messages::render() Storage::exists() őre (Messages.php:173) az
        // egyetlen dolog, ami visszatartja az újragenerálást. Ha elesik, ez a
        // sentinel eltűnik.
        Storage::disk('web')->put($path, 'SENTINEL');

        Livewire::actingAs($user)->test(Messages::class, ['group' => $group]);

        $this->assertSame('SENTINEL', Storage::disk('web')->get($path));
    }

    public function test_an_empty_board_generates_no_avatar_at_all(): void
    {
        $user = $this->createUser();
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group);

        $path = $this->avatarPathFor($user->id);

        Storage::disk('web')->delete($path);

        Livewire::actingAs($user)->test(Messages::class, ['group' => $group]);

        $this->assertFalse(Storage::disk('web')->exists($path));
    }

    public function test_the_disk_root_and_the_views_url_prefix_are_a_matched_pair(): void
    {
        // MEGFORDÍTVA a v1-patch A5 javításával.
        //
        // Korábban a `web` disk gyökere a csupasz relatív 'public' volt, a
        // nézet pedig asset('public/avatars/...')-szal címezte ugyanazt a
        // fájlt. A kettő csak WEBKÉRÉSBEN találkozott, ahol a PHP
        // munkakönyvtára maga a public/ könyvtár, tehát a relatív 'public'
        // public/public/-ra oldódott - pontosan oda, ahova az URL is mutatott.
        // Artisan, queue worker vagy teszt alatt viszont a munkakönyvtár a
        // projekt gyökere, így ugyanaz a diszk public/-ba írt, miközben a
        // nézet továbbra is public/public/-ból kérte. A régi .gitignore sora
        // (`/public/public/avatars/*`) ennek a lenyomata volt.
        //
        // Most a gyökér abszolút (public_path()), az URL-előtag pedig
        // elvesztette a duplikált 'public/' szegmenst. A pár továbbra is csak
        // EGYÜTT helyes - ezért marad ez a teszt egy fájlban a kettővel.
        $this->assertSame(public_path(), config('filesystems.disks.web.root'));

        $view = file_get_contents(resource_path('views/livewire/groups/messages.blade.php'));

        $this->assertStringContainsString("asset('avatars/avatar-'", $view);
        $this->assertStringNotContainsString("asset('public/avatars/avatar-'", $view);
    }

    // =========================================================================
    // Tripwire: amit az Intervention Image 4 elvesz - TODO 39.1 tulajdona
    // =========================================================================

    public function test_gap_stream_is_a_magic_method_that_intervention_image_4_removes(): void
    {
        $this->assertFalse(
            method_exists(Image::class, 'stream'),
            'A stream() ma NEM valódi metódus - ha az lenne, a docblock-alapú érvelés megdőlne.'
        );

        $this->assertTrue(method_exists(Image::class, '__call'));
        $this->assertTrue(class_exists('Intervention\Image\Commands\StreamCommand'));

        $this->assertFalse(
            method_exists(Image::class, 'encodeUsingFormat'),
            'Az encodeUsingFormat() a v4 API-ja; amint létezik, a hívási helyet át kell írni.'
        );

        $this->assertTrue(is_callable([Avatar::create('Test User')->getImageObject(), 'stream']));
    }

    public function test_gap_the_only_avatar_call_site_is_the_group_message_board(): void
    {
        $hits = $this->grepProjectSources('Avatar::');

        $this->assertCount(1, $hits, 'Új avatar-hívás keletkezett: '.implode(', ', $hits));
        $this->assertStringContainsString('Messages.php', $hits[0]);
    }

    public function test_gap_the_declared_constraint_admits_no_laravel_10_capable_release(): void
    {
        // A TODO 22 mérése, rögzítve. A `^4.1` felső határa 5.0.0, az első
        // L10-képes kiadás pedig pontosan 5.0.0 - tehát a kényszer semmit nem
        // enged be, és constraint edit kell, nem lock bump.
        $root = json_decode(file_get_contents(base_path('composer.json')), true);

        $this->assertSame('^4.1', $root['require']['laravolt/avatar']);

        $installed = $this->lockedPackage('laravolt/avatar');

        $this->assertSame('4.1.7', $installed['version']);
        $this->assertSame(
            '^6.0|^7.0|^8.0|^9.0',
            $installed['require']['illuminate/support'],
            'A telepített vonal a Laravel 9-nél elfogy, tehát a Phase 5-ön hasal el.'
        );

        // Az intervention/image 2-es vonala az, ami a stream()-et hozza.
        $this->assertSame('2.7.2', $this->lockedPackage('intervention/image')['version']);
    }

    // =========================================================================
    // Segédek
    // =========================================================================

    private function avatarPathFor(int $userId): string
    {
        $path = 'avatars/avatar-'.$userId.'.png';

        $this->written[] = $path;

        return $path;
    }

    private function boardWithOneMessage(): array
    {
        $user = $this->createUser();
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group);

        GroupMessage::factory()->forGroup($group)->fromUser($user)->create();

        return [$user, $group];
    }

    private function lockedPackage(string $name): array
    {
        $lock = json_decode(file_get_contents(base_path('composer.lock')), true);

        foreach (array_merge($lock['packages'], $lock['packages-dev']) as $package) {
            if ($package['name'] === $name) {
                return $package;
            }
        }

        $this->fail($name.' nincs a composer.lock-ban.');
    }

    /** @return array<int, string> "fájl:sor" találatok a projekt saját forrásaiban. */
    private function grepProjectSources(string $needle): array
    {
        $hits = [];

        foreach (['app', 'config', 'routes', 'resources'] as $directory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(base_path($directory), RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                foreach (file($file->getPathname()) as $number => $line) {
                    if (strpos($line, $needle) !== false) {
                        $hits[] = $file->getPathname().':'.($number + 1);
                    }
                }
            }
        }

        sort($hits);

        return $hits;
    }
}
