<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Allowlist sanitizer for TinyMCE-backed HTML (blog posts and similar).
 *
 * Uses HTMLPurifier so quoted links, relative /storage images, and alignment
 * survive, while scripts, event handlers, and javascript: URLs are removed.
 */
class HtmlContentSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    /** @var list<string> */
    public const ALIGNMENT_CLASSES = [
        'text-left',
        'text-center',
        'text-right',
        'text-justify',
        'alignleft',
        'aligncenter',
        'alignright',
        'alignnone',
        'align-left',
        'align-center',
        'align-right',
    ];

    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return $html;
        }

        $html = self::normalizeEditorImageSources($html);

        return trim(self::purifier()->purify($html));
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier instanceof HTMLPurifier) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('Cache.SerializerPath', self::cachePath());
        $config->set('HTML.DefinitionID', 'zanini-html-content');
        $config->set('HTML.DefinitionRev', 3);
        $config->set('HTML.Allowed', implode(',', [
            'p[class|style]',
            'br',
            'strong',
            'em',
            'b',
            'i',
            'u',
            's',
            'a[href|title|target|rel|class|style]',
            'ul[class]',
            'ol[class]',
            'li[class|style]',
            'h1[class|style]',
            'h2[class|style]',
            'h3[class|style]',
            'h4[class|style]',
            'h5[class|style]',
            'h6[class|style]',
            'img[src|alt|width|height|class|style|loading]',
            'figure[class|style]',
            'figcaption[class]',
            'blockquote[class|style]',
            'span[class|style]',
            'div[class|style]',
            'table[class|style|width]',
            'thead',
            'tbody',
            'tr',
            'td[class|style|colspan|rowspan|width]',
            'th[class|style|colspan|rowspan|width]',
            'hr',
            'sup',
            'sub',
            'pre',
            'code',
            'iframe[src|width|height|allow|allowfullscreen|frameborder|title|class]',
        ]));
        $config->set('CSS.AllowedProperties', [
            'text-align',
            'float',
            'margin',
            'margin-left',
            'margin-right',
            'margin-top',
            'margin-bottom',
            'width',
            'height',
            'max-width',
            'border-collapse',
        ]);
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
            'tel' => true,
        ]);
        $config->set('URI.DisableExternalResources', false);
        $config->set('Attr.AllowedFrameTargets', ['_blank', '_self']);
        $config->set('Attr.AllowedRel', ['noopener', 'noreferrer', 'nofollow']);
        $config->set('Attr.AllowedClasses', array_fill_keys(self::ALIGNMENT_CLASSES, true));
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');
        $config->set('HTML.TargetBlank', false);
        $config->set('HTML.Nofollow', false);
        $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', false);

        if ($def = $config->maybeGetRawHTMLDefinition()) {
            $def->addElement('figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common');
            $def->addElement('figcaption', 'Inline', 'Flow', 'Common');
            $def->addElement('s', 'Inline', 'Inline', 'Common');
            $def->addAttribute('img', 'loading', 'Enum#lazy,eager');
            $def->addAttribute('iframe', 'allowfullscreen', 'Bool');
            $def->addAttribute('iframe', 'allow', 'Text');
            $def->addAttribute('iframe', 'frameborder', 'Text');
        }

        self::$purifier = new HTMLPurifier($config);

        return self::$purifier;
    }

    private static function cachePath(): string
    {
        $path = storage_path('app/htmlpurifier');
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    /**
     * TinyMCE relative_urls can turn /storage/… into ../../storage/….
     * Normalize those (and bare storage/ paths) so the allowlist keeps them.
     */
    private static function normalizeEditorImageSources(string $html): string
    {
        $normalized = preg_replace_callback(
            '/(<img\b[^>]*\bsrc\s*=\s*)(["\'])([^"\']*)\2/i',
            static function (array $m): string {
                $src = trim(html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $src = self::normalizeStoragePath($src);

                return $m[1].$m[2].$src.$m[2];
            },
            $html
        );

        return is_string($normalized) ? $normalized : $html;
    }

    public static function normalizeStoragePath(string $src): string
    {
        $src = trim($src);
        if ($src === '') {
            return $src;
        }

        if (preg_match('#^(?:\.\./)+storage/#', $src) === 1) {
            return preg_replace('#^(?:\.\./)+storage/#', '/storage/', $src) ?? $src;
        }

        if (preg_match('#^storage/#', $src) === 1) {
            return '/'.$src;
        }

        return $src;
    }
}
