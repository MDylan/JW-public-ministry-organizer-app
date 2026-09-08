<?php

namespace Tests\Unit\Support;

use App\Support\Avatar\InitialsAvatar;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * TODO 39.1: the avatar generator that replaced `laravolt/avatar`.
 *
 * THE COLOUR CASES ARE MEASUREMENTS, NOT PREFERENCES
 *
 * Every expected hex in colourCases() was read out of the package itself while
 * it was still installed, by instantiating Laravolt\Avatar\Avatar with the old
 * config/laravolt/avatar.php and reflecting on its `background` property. They
 * are here so the migration can prove what it claims: that no existing user's
 * avatar changed colour. If someone reorders the palette in InitialsAvatar,
 * these fail - which is the point, because the order IS the mapping.
 *
 * The one case that could not be transcribed is the empty name. The package
 * seeded it with chr(rand(65, 90)) (Avatar.php:419-421), so it drew a new
 * colour on every render; twelve calls produced ten different colours. There is
 * nothing to preserve there, so the replacement is deterministic instead.
 *
 * INITIALS DIFFER ON PURPOSE
 *
 * The package ran Str::ascii() over the name before taking initials, so
 * "Kovacs Agnes" with accents gave "KA". The SVG is drawn by the browser, so it
 * can carry the real letter. This is recorded below as an explicit expectation
 * rather than left to be discovered.
 *
 * The providers are static, which PHPUnit 10 requires and 11 enforces. TODO 40
 * migrates the rest of the suite; nothing new should arrive needing that work.
 */
class InitialsAvatarTest extends TestCase
{
    /**
     * Names and the colour the DELETED package produced for them.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function colourCases(): array
    {
        return [
            'two words' => ['Test User', '#009688'],
            'accented name' => ['Kovács Ágnes', '#4CAF50'],
            'single word' => ['Nagy', '#4CAF50'],
            'single letter' => ['a', '#00BCD4'],
            'three words' => ['Jó Napot Kívánok', '#03A9F4'],
            'an e-mail address' => ['user.name@example.com', '#9C27B0'],
            'accented second word' => ['Zsolt Őrs', '#9C27B0'],
            'four words' => ['A B C D', '#9C27B0'],
        ];
    }

    #[DataProvider('colourCases')]
    public function test_the_background_matches_the_colour_the_removed_package_produced(string $name, string $expected): void
    {
        $this->assertSame($expected, InitialsAvatar::background($name));
    }

    public function test_the_background_is_stable_across_calls(): void
    {
        // The package's own weakness: for an empty name it picked at random, so
        // the same user could change colour between two renders of one page.
        $this->assertSame(
            InitialsAvatar::background('Ismételt Név'),
            InitialsAvatar::background('Ismételt Név')
        );

        $this->assertSame(InitialsAvatar::background(''), InitialsAvatar::background(''));
    }

    public function test_the_palette_is_the_colorful_theme_carried_over(): void
    {
        // A guard on the mapping as a whole: every produced colour has to come
        // out of the 15 the old config listed. A typo in one entry would
        // otherwise only surface as one user's odd avatar.
        $palette = [
            '#f44336', '#E91E63', '#9C27B0', '#673AB7', '#3F51B5',
            '#2196F3', '#03A9F4', '#00BCD4', '#009688', '#4CAF50',
            '#8BC34A', '#CDDC39', '#FFC107', '#FF9800', '#FF5722',
        ];

        $produced = [];

        foreach (range('a', 'z') as $letter) {
            $produced[] = InitialsAvatar::background($letter.'test');
        }

        $this->assertEmpty(array_diff(array_unique($produced), $palette));
        $this->assertGreaterThan(1, count(array_unique($produced)));
    }

    // =========================================================================
    // Initials
    // =========================================================================

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function initialsCases(): array
    {
        return [
            'two words give one letter each' => ['Test User', 'TU'],
            'a single word gives its first two' => ['Nagy', 'NA'],
            'one letter stays one letter' => ['a', 'A'],
            'only the first two words count' => ['A B C D', 'AB'],
            'an e-mail address is read as a name' => ['user.name@example.com', 'UN'],
            'an empty name gives nothing' => ['', ''],
            'whitespace only gives nothing' => ['   ', ''],
            'a double space is not an empty initial' => ['Test  User', 'TU'],
            // The package returned the unaccented letters here - see the docblock.
            'accents survive' => ['Kovács Ágnes', 'KÁ'],
            'accents survive in the second word' => ['Zsolt Őrs', 'ZŐ'],
        ];
    }

    #[DataProvider('initialsCases')]
    public function test_the_initials_follow_the_generator_rules(string $name, string $expected): void
    {
        $this->assertSame($expected, InitialsAvatar::initials($name));
    }

    // =========================================================================
    // The markup
    // =========================================================================

    public function test_the_svg_is_well_formed_and_carries_the_namespace(): void
    {
        $svg = InitialsAvatar::svg('Test User');

        // Without xmlns an SVG referenced from an <img> renders as nothing at
        // all, and nothing reports the failure.
        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $svg);
        $this->assertNotFalse(simplexml_load_string($svg));
        $this->assertStringContainsString('#009688', $svg);
        $this->assertStringContainsString('>TU<', $svg);
    }

    public function test_the_data_uri_decodes_back_to_the_svg(): void
    {
        $uri = InitialsAvatar::dataUri('Test User');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);

        $decoded = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')), true);

        $this->assertSame(InitialsAvatar::svg('Test User'), $decoded);
    }

    public function test_a_name_cannot_inject_markup_into_the_svg(): void
    {
        // The name is user input, and base64 is NOT a defence: the browser
        // decodes the data URI and parses the result as SVG. An unescaped '<'
        // would open an element here.
        $svg = InitialsAvatar::svg('<script>alert(1)</script> Xavér');

        $this->assertStringNotContainsString('<script', $svg);
        $this->assertStringContainsString('&lt;', $svg);
        $this->assertNotFalse(simplexml_load_string($svg));

        $quoted = InitialsAvatar::svg('"onload="alert(1) Y');

        $this->assertStringNotContainsString('onload=', $quoted);
        $this->assertNotFalse(simplexml_load_string($quoted));
    }

    public function test_an_empty_name_still_produces_a_usable_avatar(): void
    {
        $svg = InitialsAvatar::svg('');

        $this->assertNotFalse(simplexml_load_string($svg));
        $this->assertStringContainsString('<circle', $svg);
    }
}
