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

        $html = self::stripHtmlComments($html);
        $html = self::normalizeEditorImageSources($html);
        $html = self::unwrapMisusedCodeBlocks($html);
        $html = trim(self::purifier()->purify($html));
        $html = self::removeEmptyHeadingsAndParagraphs($html);

        return trim($html);
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

    public static function stripHtmlComments(string $html): string
    {
        $stripped = preg_replace('/<!--.*?-->/s', '', $html);

        return is_string($stripped) ? $stripped : $html;
    }

    /**
     * Gemini often wraps a lone image or a prose sentence in div>pre>code.
     * Those blocks do not render as article content — unwrap them. Keep real code.
     */
    private static function unwrapMisusedCodeBlocks(string $html): string
    {
        if (stripos($html, '<pre') === false && stripos($html, '<code') === false) {
            return $html;
        }

        $dom = self::loadFragment($html);
        if (! $dom) {
            return $html;
        }

        $xpath = new \DOMXPath($dom);
        foreach (['//code', '//pre'] as $query) {
            $nodes = iterator_to_array($xpath->query($query) ?: []);
            foreach (array_reverse($nodes) as $node) {
                if (! $node instanceof \DOMElement || ! $node->parentNode) {
                    continue;
                }
                if (self::shouldUnwrapCodeish($node)) {
                    self::replaceNodeWithChildren($node);
                }
            }
        }

        self::unwrapCodeWrapperDivs($dom);

        return self::saveFragment($dom) ?? $html;
    }

    private static function shouldUnwrapCodeish(\DOMElement $node): bool
    {
        if (self::looksLikeRealCode($node)) {
            return false;
        }

        return self::isImageOrProseOnly($node);
    }

    private static function looksLikeRealCode(\DOMElement $node): bool
    {
        $images = $node->getElementsByTagName('img');
        if ($images->length > 0 && self::onlyImagesAndBlank($node)) {
            return false;
        }

        $text = $node->textContent ?? '';
        if (preg_match('/<[a-z][^>]*>/i', $text) && self::elementDescendantCount($node) === 0) {
            return true;
        }

        if (preg_match('/\bfunction\s+\w+\s*\(/', $text)) {
            return true;
        }
        if (preg_match('/\b(const|let|var)\s+\w+\s*=/', $text)) {
            return true;
        }
        if (preg_match('/[{}]/', $text) && preg_match('/;/', $text)) {
            return true;
        }
        if (substr_count($text, "\n") >= 2 && preg_match('/[{}=;]/', $text)) {
            return true;
        }

        return false;
    }

    private static function isImageOrProseOnly(\DOMElement $node): bool
    {
        if (self::onlyImagesAndBlank($node)) {
            return $node->getElementsByTagName('img')->length > 0
                || self::visualText($node) === '';
        }

        $allowed = ['p', 'br', 'strong', 'em', 'b', 'i', 'u', 's', 'a', 'span', 'img', 'code'];
        foreach ($node->getElementsByTagName('*') as $el) {
            if (! in_array(strtolower($el->tagName), $allowed, true)) {
                return false;
            }
        }

        return self::visualText($node) !== '' || $node->getElementsByTagName('img')->length > 0;
    }

    private static function elementDescendantCount(\DOMElement $node): int
    {
        $count = 0;
        foreach ($node->getElementsByTagName('*') as $ignored) {
            $count++;
        }

        return $count;
    }

    private static function onlyImagesAndBlank(\DOMElement $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                if (self::visualTextValue($child->textContent) !== '') {
                    return false;
                }
                continue;
            }
            if (! $child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if ($tag === 'br') {
                continue;
            }
            if ($tag === 'code' || $tag === 'span') {
                if (! self::onlyImagesAndBlank($child)) {
                    return false;
                }
                continue;
            }
            if ($tag !== 'img') {
                return false;
            }
        }

        return true;
    }

    private static function unwrapCodeWrapperDivs(\DOMDocument $dom): void
    {
        $xpath = new \DOMXPath($dom);
        $divs = iterator_to_array($xpath->query('//div') ?: []);
        foreach (array_reverse($divs) as $div) {
            if (! $div instanceof \DOMElement || ! $div->parentNode) {
                continue;
            }
            if ($div->getAttribute('id') === 'zr-root') {
                continue;
            }
            $class = $div->getAttribute('class');
            $isCodeWrap = $class !== '' && preg_match('/highlight|codehilite|language-|hljs|sourcecode|code-block/i', $class);
            $kids = [];
            foreach ($div->childNodes as $child) {
                if ($child instanceof \DOMText && self::visualTextValue($child->textContent) === '') {
                    continue;
                }
                $kids[] = $child;
            }
            if (count($kids) !== 1 || ! $kids[0] instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($kids[0]->tagName);
            if ($tag === 'img' || $isCodeWrap) {
                self::replaceNodeWithChildren($div);
            }
        }
    }

    private static function removeEmptyHeadingsAndParagraphs(string $html): string
    {
        if ($html === '' || (stripos($html, '<h') === false && stripos($html, '<p') === false)) {
            return $html;
        }

        $dom = self::loadFragment($html);
        if (! $dom) {
            return $html;
        }

        $xpath = new \DOMXPath($dom);
        $nodes = iterator_to_array($xpath->query('//h1|//h2|//h3|//h4|//h5|//h6|//p') ?: []);
        foreach ($nodes as $node) {
            if (! $node instanceof \DOMElement || ! $node->parentNode) {
                continue;
            }
            if (self::isVisuallyEmptyBlock($node)) {
                $node->parentNode->removeChild($node);
            }
        }

        return self::saveFragment($dom) ?? $html;
    }

    private static function isVisuallyEmptyBlock(\DOMElement $node): bool
    {
        if ($node->getElementsByTagName('img')->length > 0
            || $node->getElementsByTagName('iframe')->length > 0) {
            return false;
        }

        return self::visualText($node) === '';
    }

    private static function visualText(\DOMElement $node): string
    {
        return self::visualTextValue($node->textContent ?? '');
    }

    private static function visualTextValue(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\x{00A0}|\x{200B}|\x{200C}|\x{200D}|\x{FEFF}/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private static function replaceNodeWithChildren(\DOMElement $node): void
    {
        $parent = $node->parentNode;
        if (! $parent) {
            return;
        }

        $tag = strtolower($node->tagName);
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        if ($children === [] && ($tag === 'pre' || $tag === 'code')) {
            $parent->removeChild($node);

            return;
        }

        $onlyText = $children !== [] && ! array_filter($children, fn ($c) => ! $c instanceof \DOMText);
        if ($onlyText && ($tag === 'pre' || $tag === 'code')) {
            $text = self::visualText($node);
            if ($text !== '') {
                $p = $node->ownerDocument->createElement('p');
                $p->appendChild($node->ownerDocument->createTextNode($text));
                $parent->insertBefore($p, $node);
            }
            $parent->removeChild($node);

            return;
        }

        foreach ($children as $child) {
            $parent->insertBefore($child, $node);
        }
        $parent->removeChild($node);
    }

    private static function loadFragment(string $html): ?\DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="zr-root">'.$html.'</div>',
            LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $ok ? $dom : null;
    }

    private static function saveFragment(\DOMDocument $dom): ?string
    {
        $xpath = new \DOMXPath($dom);
        $root = $xpath->query('//*[@id="zr-root"]')->item(0);
        if (! $root) {
            $root = $dom->getElementsByTagName('body')->item(0);
        }
        if (! $root) {
            return null;
        }

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
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
