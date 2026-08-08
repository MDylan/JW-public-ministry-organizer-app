<?php

namespace Tests\Feature\Setup;

use App\Http\Middleware\EnsureInstallerToken;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * A v1-patch D2: a telepítő hozzáférés-védelme.
 *
 * A MÉRT HIBA
 *
 * A `setup/*` útvonalcsoport egyetlen jogosultsági ellenőrzést sem hordozott -
 * se `auth`, se gate, se aláírás. A telepítési ablakban tehát BÁRKI, aki
 * ismerte a címet, létrehozhatott `mainAdmin` fiókot, ráadásul ismételten, és
 * bárki lezárhatta a telepítőt, mert a `setup.complete` egyszerű GET-en írta ki
 * a sentinel fájlt - amitől a csoport többé nem regisztrálódik, vagyis a valódi
 * telepítés BEFEJEZHETETLENNÉ vált.
 *
 * A VÁLASZTOTT VÉDELEM
 *
 * Bejelentkezéshez kötni nem lehet: a telepítés pontosan az a szakasz, amikor
 * még nincs felhasználó. A middleware ezért fájlrendszer-hozzáférést
 * bizonyíttat - a tokent a szerveren, a `storage/app/installer-token.txt`
 * fájlban kell elolvasni.
 *
 * Ez az egyetlen fájl a `tests/Feature/Setup/` alatt, amelyik NEM oldja fel a
 * kaput a setUp()-ban - a többi leszármazott a SetupTestCase-ből örökli a
 * feloldást, hogy továbbra is azt mérje, amiért íródott.
 */
class InstallerAccessTest extends SetupTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A SetupTestCase alapból felold; itt épp a zárt állapot a tárgy.
        $this->flushSession();
    }

    // =========================================================================
    // 1. A kapu
    // =========================================================================

    /**
     * @dataProvider guardedRouteProvider
     */
    public function test_a_visitor_without_the_token_is_turned_away(string $route): void
    {
        $this->get(route($route))->assertRedirect(route('setup.welcome'));
    }

    public static function guardedRouteProvider(): array
    {
        return [
            'requirements' => ['setup.requirements'],
            'basics'       => ['setup.basics'],
            'database'     => ['setup.database'],
            'mail'         => ['setup.mail'],
            'account'      => ['setup.account'],
            'complete'     => ['setup.complete'],
        ];
    }

    public function test_a_visitor_without_the_token_cannot_create_an_administrator(): void
    {
        // EZ A LÉNYEG. A telepítési ablakban bárki, aki ismerte a címet,
        // létrehozhatott magának mainAdmin fiókot.
        $this->post(route('setup.save-account'), [
            'name'                  => 'Betolakodó',
            'email'                 => 'intruder@example.test',
            'password'              => 'Titkos-Jelszo1',
            'password_confirmation' => 'Titkos-Jelszo1',
        ])->assertRedirect(route('setup.welcome'));

        $this->assertSame(0, User::count(), 'Egyetlen fiók sem jöhet létre.');
    }

    public function test_a_visitor_without_the_token_cannot_close_the_installer(): void
    {
        // A sentinel kiírása lezárja a telepítőt: a route-csoport onnantól nem
        // regisztrálódik. Ha ezt egy idegen kiválthatja, a valódi telepítés
        // befejezhetetlenné válik.
        $this->get(route('setup.complete'))->assertRedirect(route('setup.welcome'));

        $this->assertFileDoesNotExist($this->sentinelPath());
    }

    public function test_the_welcome_screen_stays_open_because_the_token_goes_in_there(): void
    {
        $this->get(route('setup.welcome'))->assertStatus(200);
    }

    // =========================================================================
    // 2. A feloldás
    // =========================================================================

    public function test_the_token_file_is_created_on_demand_and_is_not_guessable(): void
    {
        // A SetupTestCase::setUp() feloldáskor már kikényszerítette a fájlt;
        // itt épp a keletkezése a tárgy, ezért eldobjuk.
        EnsureInstallerToken::forget();

        $this->assertFalse(Storage::exists(EnsureInstallerToken::TOKEN_FILE));

        $token = EnsureInstallerToken::currentToken();

        $this->assertTrue(Storage::exists(EnsureInstallerToken::TOKEN_FILE));
        $this->assertSame(32, strlen($token));

        // Ismételt olvasás ugyanazt adja - nem generálunk újat minden kérésre.
        $this->assertSame($token, EnsureInstallerToken::currentToken());
    }

    public function test_a_wrong_token_is_rejected(): void
    {
        EnsureInstallerToken::currentToken();

        $this->post(route('setup.unlock'), ['token' => 'nem-ez-az'])
            ->assertSessionHasErrors('token');

        $this->get(route('setup.requirements'))->assertRedirect(route('setup.welcome'));
    }

    public function test_the_right_token_unlocks_the_rest_of_the_installer(): void
    {
        $token = EnsureInstallerToken::currentToken();

        $this->post(route('setup.unlock'), ['token' => $token])
            ->assertRedirect(route('setup.requirements'));

        $this->get(route('setup.requirements'))->assertStatus(200);
    }

    public function test_the_token_is_accepted_with_surrounding_whitespace(): void
    {
        // Fájlból másolva könnyen jön vele sortörés vagy szóköz; ezen ne
        // bukjon el egy telepítés.
        $token = EnsureInstallerToken::currentToken();

        $this->post(route('setup.unlock'), ['token' => "  \n".$token."  \n"])
            ->assertRedirect(route('setup.requirements'));
    }

    // =========================================================================
    // 3. A lezárás feltétele
    // =========================================================================

    public function test_the_installer_cannot_be_closed_before_an_administrator_exists(): void
    {
        // A sentinel jelentése "a telepítés befejeződött", és az pontosan azt
        // jelenti, hogy van adminisztrátori fiók. Korábban a GET feltétel
        // nélkül kiírta.
        $this->unlockInstaller();

        $this->get(route('setup.complete'))->assertRedirect(route('setup.account'));

        $this->assertFileDoesNotExist($this->sentinelPath());
    }

    public function test_the_installer_closes_once_an_administrator_exists(): void
    {
        $this->unlockInstaller();

        User::factory()->create(['role' => 'mainAdmin', 'email' => 'admin@example.test']);

        $this->get(route('setup.complete'))->assertStatus(200);

        $this->assertFileExists($this->sentinelPath());
    }

    public function test_closing_the_installer_removes_the_token_file(): void
    {
        // A token elveszti a jelentését; ne maradjon a lemezen egy olyan fájl,
        // ami egyszer hozzáférést adott.
        $this->unlockInstaller();
        EnsureInstallerToken::currentToken();

        User::factory()->create(['role' => 'mainAdmin', 'email' => 'admin2@example.test']);

        $this->get(route('setup.complete'))->assertStatus(200);

        $this->assertFalse(Storage::exists(EnsureInstallerToken::TOKEN_FILE));
    }
}
