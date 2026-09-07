<?php

namespace App\Support\Avatar;

use Illuminate\Support\Str;

/**
 * TODO 39.1: initials avatars, computed instead of rendered.
 *
 * WHY THIS REPLACED A PACKAGE
 *
 * Until now the group message board generated a 100x100 PNG per author with
 * `laravolt/avatar` on top of `intervention/image`, wrote it to the `web` disk
 * and served it as a static file. That cost two dependencies and a forced
 * Intervention Image 2 -> 4 migration at the Laravel 10 hop, for one call site
 * (Groups\Messages::render()) drawing two letters on a coloured circle.
 *
 * An SVG data URI produces the same picture with no image library, no GD, and
 * no file. The circle is drawn by the SVG itself rather than left to the
 * .direct-chat-img `border-radius`, so the avatar stays round wherever it is
 * used.
 *
 * COMPATIBILITY IS DELIBERATE, NOT INCIDENTAL
 *
 * The colour picking below is a transcription of the package's own
 * `Avatar::getRandomElement()` (vendor Avatar.php:427-435): the sum of the
 * name's BYTES modulo the palette size. Bytes, not characters - and the RAW
 * name, before the e-mail and ASCII handling that only ever applied to the
 * initials. That is what keeps every existing user on the colour they already
 * had, which is the whole reason this class does not simply hash the name.
 *
 * The palette is the 15-colour `colorful` theme carried over from the deleted
 * config/laravolt/avatar.php, in its original order. The order is load-bearing:
 * a reordering silently reassigns everyone's colour.
 *
 * TWO INTENTIONAL DIFFERENCES FROM THE PACKAGE
 *
 * 1. Accented initials survive. The package ran Str::ascii() over the name, so
 *    "Ágnes" started with an "A"; the browser draws the real letter, so it is
 *    now "Á". Nothing rendered the ASCII form better - the fonts the config
 *    pointed at did not exist (config/fonts/ was never published), so the old
 *    PNGs fell back to GD's built-in bitmap font.
 * 2. An empty name is deterministic. The package picked a RANDOM letter for it
 *    (Avatar.php:419-421), i.e. a random colour on every single render; here it
 *    is the first palette entry and no initials.
 */
class InitialsAvatar
{
    /**
     * The `colorful` theme's backgrounds, in the package's original order.
     *
     * @var array<int, string>
     */
    private const BACKGROUNDS = [
        '#f44336',
        '#E91E63',
        '#9C27B0',
        '#673AB7',
        '#3F51B5',
        '#2196F3',
        '#03A9F4',
        '#00BCD4',
        '#009688',
        '#4CAF50',
        '#8BC34A',
        '#CDDC39',
        '#FFC107',
        '#FF9800',
        '#FF5722',
    ];

    /** The `colorful` theme's single foreground. */
    private const FOREGROUND = '#FFFFFF';

    /** Initials, as the package's DefaultGenerator counted them. */
    private const CHARS = 2;

    /** The font size the old config asked for, in viewBox units. */
    private const FONT_SIZE = 38;

    /**
     * The `src` of an <img>: a base64 SVG data URI.
     *
     * Base64 rather than raw UTF-8, because the raw form would have to escape
     * '#' and every other character the URL grammar reserves, and one missed
     * escape is a broken image rather than a visible error.
     */
    public static function dataUri(string $name): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($name));
    }

    /**
     * The avatar markup.
     *
     * The xmlns declaration is REQUIRED: an SVG referenced from an <img> is
     * parsed as a standalone document, and without the namespace the browser
     * renders nothing at all.
     *
     * dy=".35em" instead of dominant-baseline, because the latter is ignored by
     * some renderers and the text would sit on the circle's centre line.
     */
    public static function svg(string $name): string
    {
        $initials = self::initials($name);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100">'
            .'<circle cx="50" cy="50" r="50" fill="'.self::background($name).'"/>'
            .'<text x="50" y="50" dy=".35em" text-anchor="middle" '
            .'font-family="Helvetica, Arial, sans-serif" font-size="'.self::FONT_SIZE.'" '
            .'font-weight="bold" fill="'.self::FOREGROUND.'">'
            .self::escape($initials)
            .'</text>'
            .'</svg>';
    }

    /**
     * Up to two uppercase initials, following the package's DefaultGenerator.
     *
     * The rules, in its order: an e-mail address becomes "local part with dots
     * as spaces"; a single word contributes its first two characters; several
     * words contribute one character each.
     *
     * Str::upper() rather than strtoupper(), because the latter is byte-wise
     * and would leave a multi-byte letter untouched. The package could use
     * strtoupper() only because it had already flattened the name to ASCII.
     */
    public static function initials(string $name): string
    {
        $name = trim($name);

        if (filter_var($name, FILTER_VALIDATE_EMAIL)) {
            $name = str_replace('.', ' ', Str::before($name, '@'));
        }

        $words = array_values(array_filter(explode(' ', $name), static function ($word) {
            return $word !== '';
        }));

        if ($words === []) {
            return '';
        }

        if (count($words) === 1) {
            return Str::upper(Str::substr($words[0], 0, self::CHARS));
        }

        $initials = '';

        foreach (array_slice($words, 0, self::CHARS) as $word) {
            $initials .= Str::substr($word, 0, 1);
        }

        return Str::upper($initials);
    }

    /**
     * The background colour for a name.
     *
     * The package's algorithm, byte for byte - see the class docblock for why
     * it is transcribed rather than improved.
     */
    public static function background(string $name): string
    {
        if ($name === '') {
            return self::BACKGROUNDS[0];
        }

        $sum = 0;

        for ($i = 0, $length = strlen($name); $i < $length; $i++) {
            $sum += ord($name[$i]);
        }

        return self::BACKGROUNDS[$sum % count(self::BACKGROUNDS)];
    }

    /**
     * Markup escaping for the initials.
     *
     * The name is user input and lands inside a <text> element. Base64 is NOT a
     * defence: the browser decodes the data URI and parses the result as SVG,
     * so an unescaped '<' would open an element - script included.
     */
    private static function escape(string $initials): string
    {
        return htmlspecialchars($initials, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
