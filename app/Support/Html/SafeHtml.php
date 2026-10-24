<?php

namespace App\Support\Html;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Group-authored rich text, made safe to print raw.
 *
 * Roadmap TODO 70: a group's news content and its posters' text come from a
 * Summernote editor and were printed with {!! !!} unfiltered, so a roler -
 * or anyone who reached the forms - could store a script that ran in every
 * member's session, a group admin's included. This keeps what the editor
 * produces (formatting, lists, links, tables, images) and drops scripts,
 * event handler attributes, javascript: URLs, frames, forms and styles
 * beyond a short list.
 *
 * It filters on OUTPUT, so content stored before the fix is covered too.
 * Content written by the site's main admin (static pages, newsletters, the
 * home page message) is left as it is: that author can already do anything.
 */
final class SafeHtml
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return self::purifier()->purify($html);
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.Allowed', implode(',', [
            'p[style]', 'div[style]', 'span[style]', 'br', 'hr',
            'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'sub', 'sup',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code',
            'ul', 'ol', 'li',
            'a[href|title|target]',
            'img[src|alt|title|width|height|style]',
            'font[color|face|size]',
            'table[style]', 'thead', 'tbody', 'tr', 'th[colspan|rowspan|style]', 'td[colspan|rowspan|style]',
        ]));
        $config->set('CSS.AllowedProperties', [
            'text-align', 'color', 'background-color', 'font-weight', 'font-style',
            'text-decoration', 'font-size', 'font-family', 'line-height',
            'width', 'height', 'float', 'margin', 'margin-left', 'margin-right',
        ]);
        // data: only resolves to images (HTMLPurifier's data scheme accepts
        // image/png, image/gif and image/jpeg) - Summernote embeds pasted
        // pictures that way.
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true, 'data' => true]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('HTML.TargetNoopener', true);
        $config->set('HTML.TargetNoreferrer', true);

        $cache = storage_path('framework/cache/htmlpurifier');
        if (is_dir($cache) || @mkdir($cache, 0775, true)) {
            $config->set('Cache.SerializerPath', $cache);
        } else {
            $config->set('Cache.DefinitionImpl', null);
        }

        return self::$purifier = new HTMLPurifier($config);
    }
}
