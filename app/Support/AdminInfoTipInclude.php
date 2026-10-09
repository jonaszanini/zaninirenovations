<?php

namespace App\Support;

use InvalidArgumentException;

class AdminInfoTipInclude
{
    public const MESSAGE = "admin.partials.info_tip no longer accepts 'text' or 'html'; pass tipText/tipHtml.";

    public const INFO_TIP_VIEW = 'admin.partials.info_tip';

    public const FIELD_HINT_VIEW = 'admin.client_projects._cp_field_hint';

    public const TIPPED_LABEL_VIEW = 'admin.partials.tipped_label';

    /**
     * @param  array<string, mixed>  $data
     */
    public static function assertNoLegacyKeys(array $data): void
    {
        if (! array_key_exists('text', $data) && ! array_key_exists('html', $data)) {
            return;
        }

        if (app()->environment('production')) {
            logger()->error(self::MESSAGE);

            return;
        }

        throw new InvalidArgumentException(self::MESSAGE);
    }

    /**
     * @param  array<string, mixed>  $defined
     * @return array{tipId: mixed, tipLabel: mixed, tipText: mixed, tipHtml: mixed}
     */
    public static function resolve(array $defined): array
    {
        self::assertNoLegacyKeys($defined);

        return [
            'tipId' => array_key_exists('tipId', $defined) ? $defined['tipId'] : null,
            'tipLabel' => array_key_exists('tipLabel', $defined) ? $defined['tipLabel'] : null,
            'tipText' => array_key_exists('tipText', $defined) ? $defined['tipText'] : null,
            'tipHtml' => array_key_exists('tipHtml', $defined) ? $defined['tipHtml'] : null,
        ];
    }

    /**
     * Same as resolve(), for view() / ->with() data bags (includes shared keys).
     *
     * @param  array<string, mixed>  $viewData
     * @return array{tipId: mixed, tipLabel: mixed, tipText: mixed, tipHtml: mixed}
     */
    public static function resolveFromView(array $viewData): array
    {
        return self::resolve($viewData);
    }

    /**
     * Render the tip from an explicit data bag only — never a parent Blade scope.
     *
     * @param  array<string, mixed>  $data
     */
    public static function render(array $data = []): string
    {
        $resolved = self::resolve($data);
        if (! filled($resolved['tipHtml']) && ! filled($resolved['tipText'])) {
            return '';
        }

        return view(self::INFO_TIP_VIEW, $resolved)->render();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function renderHint(array $data = []): string
    {
        return self::render($data);
    }

    /**
     * view() / ->with() entry: only the four tip* keys from the view data bag.
     *
     * @param  array<string, mixed>  $viewData
     */
    public static function renderFromView(array $viewData): string
    {
        return self::render($viewData);
    }

    /**
     * Render a label + ⓘ from an explicit data bag only — never a parent Blade scope.
     *
     * @param  array<string, mixed>  $data
     */
    public static function renderTippedLabel(array $data = []): string
    {
        return view(self::TIPPED_LABEL_VIEW, self::tippedLabelBag($data))->render();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function tippedLabelBag(array $data): array
    {
        $bag = [];
        foreach (['label', 'for', 'tipText', 'tipHtml', 'tipLabel', 'tipId', 'required', 'labelId', 'prefixHtml'] as $key) {
            if (array_key_exists($key, $data)) {
                $bag[$key] = $data[$key];
            }
        }

        return $bag;
    }

    /**
     * Stable control id from a form name so cloned / templated rows
     * (`pieces[__P__][edge_banding]`) keep a rewriteable id instead of uniqid.
     */
    public static function controlIdFromName(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }
        $id = preg_replace('/[^A-Za-z0-9_-]+/', '-', $name);
        $id = trim((string) $id, '-');

        return $id !== '' ? $id : null;
    }

    /**
     * Keep the last word with the ⓘ so two-column labels wrap as
     * "Finished" / "rooms ⓘ" instead of "Finished rooms" / "ⓘ".
     *
     * @return array{lead: string, last: string}
     */
    public static function splitLabel(string $label): array
    {
        $label = trim((string) preg_replace('/\s+/u', ' ', $label));
        if ($label === '') {
            return ['lead' => '', 'last' => ''];
        }
        $pos = mb_strrpos($label, ' ');
        if ($pos === false) {
            return ['lead' => '', 'last' => $label];
        }

        return [
            'lead' => mb_substr($label, 0, $pos),
            'last' => mb_substr($label, $pos + 1),
        ];
    }

    /**
     * Split a (possibly HTML) label on the last text word without leaving
     * unclosed tags. `<strong>Client provides all materials</strong>` becomes
     * lead `<strong>Client provides all</strong>` + last `<strong>materials</strong>`.
     *
     * @return array{lead: string, last: string, lastIsHtml: bool}
     */
    public static function splitLabelHtml(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return ['lead' => '', 'last' => '', 'lastIsHtml' => false];
        }
        if (! preg_match('/<[^>]+>/', $html)) {
            $parts = self::splitLabel($html);

            return [
                'lead' => $parts['lead'],
                'last' => $parts['last'],
                'lastIsHtml' => false,
            ];
        }

        preg_match_all('/<[^>]+>|[^<]+/u', $html, $matches);
        $tokens = $matches[0];
        $lastTokenIdx = null;
        $wordStart = 0;
        $lastWord = '';
        for ($i = count($tokens) - 1; $i >= 0; $i--) {
            if (isset($tokens[$i][0]) && $tokens[$i][0] === '<') {
                continue;
            }
            if (! preg_match('/\S/u', $tokens[$i])) {
                continue;
            }
            $trimmed = preg_replace('/\s+$/u', '', $tokens[$i]);
            $pos = mb_strrpos($trimmed, ' ');
            if ($pos === false) {
                $lastWord = $trimmed;
                $wordStart = 0;
            } else {
                $lastWord = mb_substr($trimmed, $pos + 1);
                $wordStart = $pos + 1;
            }
            $lastTokenIdx = $i;
            break;
        }
        if ($lastTokenIdx === null || $lastWord === '') {
            return ['lead' => $html, 'last' => '', 'lastIsHtml' => false];
        }

        $leadTokens = array_slice($tokens, 0, $lastTokenIdx);
        $leadPiece = mb_substr($tokens[$lastTokenIdx], 0, $wordStart);
        $leadTokens[] = rtrim($leadPiece);
        $after = array_slice($tokens, $lastTokenIdx + 1);

        $open = [];
        $void = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
        foreach ($leadTokens as $token) {
            if (! preg_match('/^<(\/?)([A-Za-z][A-Za-z0-9]*)/', $token, $m)) {
                continue;
            }
            $tag = strtolower($m[2]);
            if ($m[1] === '/') {
                for ($j = count($open) - 1; $j >= 0; $j--) {
                    if ($open[$j] === $tag) {
                        array_splice($open, $j, 1);
                        break;
                    }
                }

                continue;
            }
            if (in_array($tag, $void, true) || preg_match('/\/\s*>$/', $token)) {
                continue;
            }
            $open[] = $tag;
        }

        $closeLead = '';
        $reopenLast = '';
        for ($j = count($open) - 1; $j >= 0; $j--) {
            $closeLead .= '</'.$open[$j].'>';
        }
        foreach ($open as $tag) {
            $reopenLast .= '<'.$tag.'>';
        }

        $lead = rtrim(implode('', $leadTokens)).$closeLead;
        $last = $reopenLast.$lastWord.implode('', $after);

        return [
            'lead' => $lead,
            'last' => $last,
            'lastIsHtml' => $last !== strip_tags($last),
        ];
    }

    /**
     * Rewrite @include of the tip partials to explicit PHP renders so parent
     * Blade scope cannot leak tipHtml / tipLabel / tipId / tipText.
     */
    public static function rewriteBlade(string $value): string
    {
        $value = self::rewriteIncludeCalls($value, self::INFO_TIP_VIEW, 'render');
        $value = self::rewriteIncludeCalls($value, self::FIELD_HINT_VIEW, 'renderHint');
        $value = self::rewriteIncludeCalls($value, self::TIPPED_LABEL_VIEW, 'renderTippedLabel');
        $value = self::rewriteWhenUnless($value, 'includeWhen', self::INFO_TIP_VIEW, 'render');
        $value = self::rewriteWhenUnless($value, 'includeWhen', self::FIELD_HINT_VIEW, 'renderHint');
        $value = self::rewriteWhenUnless($value, 'includeWhen', self::TIPPED_LABEL_VIEW, 'renderTippedLabel');
        $value = self::rewriteWhenUnless($value, 'includeUnless', self::INFO_TIP_VIEW, 'render');
        $value = self::rewriteWhenUnless($value, 'includeUnless', self::FIELD_HINT_VIEW, 'renderHint');
        $value = self::rewriteWhenUnless($value, 'includeUnless', self::TIPPED_LABEL_VIEW, 'renderTippedLabel');
        $value = self::rewriteIncludeFirst($value, self::INFO_TIP_VIEW, 'render');
        $value = self::rewriteIncludeFirst($value, self::FIELD_HINT_VIEW, 'renderHint');
        $value = self::rewriteIncludeFirst($value, self::TIPPED_LABEL_VIEW, 'renderTippedLabel');

        return $value;
    }

    private static function rewriteIncludeCalls(string $value, string $view, string $method): string
    {
        foreach (['includeIf', 'include'] as $directive) {
            $value = self::replaceDirectiveCalls($value, $directive, $view, function (string $args) use ($method) {
                $data = self::dataArgAfterViewName($args);

                return '<?php echo \\App\\Support\\AdminInfoTipInclude::'.$method.'('.$data.'); ?>';
            });
        }

        return $value;
    }

    private static function rewriteWhenUnless(string $value, string $directive, string $view, string $method): string
    {
        return self::replaceDirectiveCalls($value, $directive, $view, function (string $args) use ($method, $view, $directive) {
            $parts = self::splitTopLevelArgs($args);
            $condition = trim($parts[0] ?? 'false');
            $data = '[]';
            foreach ($parts as $i => $part) {
                if ($i === 0) {
                    continue;
                }
                if (self::argIsViewName($part, $view)) {
                    continue;
                }
                $data = trim($part);
            }
            $echo = '\\App\\Support\\AdminInfoTipInclude::'.$method.'('.$data.')';
            if ($directive === 'includeUnless') {
                return '<?php if (! ('.$condition.')) echo '.$echo.'; ?>';
            }

            return '<?php if ('.$condition.') echo '.$echo.'; ?>';
        }, 1);
    }

    private static function rewriteIncludeFirst(string $value, string $view, string $method): string
    {
        $offset = 0;
        $out = '';
        while (preg_match('/@includeFirst\s*\(/', $value, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1];
            $open = $start + strlen($m[0][0]) - 1;
            $close = self::matchingParen($value, $open);
            if ($close === null) {
                break;
            }
            $args = substr($value, $open + 1, $close - $open - 1);
            if (! str_contains($args, $view)) {
                $out .= substr($value, $offset, $close + 1 - $offset);
                $offset = $close + 1;

                continue;
            }
            $parts = self::splitTopLevelArgs($args);
            $data = isset($parts[1]) ? trim($parts[1]) : '[]';
            $out .= substr($value, $offset, $start - $offset);
            $out .= '<?php echo \\App\\Support\\AdminInfoTipInclude::'.$method.'('.$data.'); ?>';
            $offset = $close + 1;
        }

        return $out.substr($value, $offset);
    }

    /**
     * @param  callable(string): string  $replacer
     */
    private static function replaceDirectiveCalls(string $value, string $directive, string $view, callable $replacer, int $viewArgIndex = 0): string
    {
        $offset = 0;
        $out = '';
        $pattern = '/@'.preg_quote($directive, '/').'\s*\(/';
        while (preg_match($pattern, $value, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1];
            $open = $start + strlen($m[0][0]) - 1;
            $close = self::matchingParen($value, $open);
            if ($close === null) {
                break;
            }
            $args = substr($value, $open + 1, $close - $open - 1);
            $parts = self::splitTopLevelArgs($args);
            $viewArg = $parts[$viewArgIndex] ?? '';
            if (! self::argIsViewName($viewArg, $view)) {
                $out .= substr($value, $offset, $close + 1 - $offset);
                $offset = $close + 1;

                continue;
            }
            $out .= substr($value, $offset, $start - $offset);
            $out .= $replacer($args);
            $offset = $close + 1;
        }

        return $out.substr($value, $offset);
    }

    private static function dataArgAfterViewName(string $args): string
    {
        $parts = self::splitTopLevelArgs($args);

        return isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : '[]';
    }

    private static function argIsViewName(string $arg, string $view): bool
    {
        $arg = trim($arg);

        return $arg === "'{$view}'" || $arg === '"'.$view.'"';
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevelArgs(string $args): array
    {
        $parts = [];
        $depth = 0;
        $quote = null;
        $current = '';
        $len = strlen($args);
        for ($i = 0; $i < $len; $i++) {
            $ch = $args[$i];
            if ($quote !== null) {
                $current .= $ch;
                if ($ch === '\\' && $i + 1 < $len) {
                    $current .= $args[++$i];

                    continue;
                }
                if ($ch === $quote) {
                    $quote = null;
                }

                continue;
            }
            if ($ch === "'" || $ch === '"') {
                $quote = $ch;
                $current .= $ch;

                continue;
            }
            if ($ch === '(' || $ch === '[' || $ch === '{') {
                $depth++;
                $current .= $ch;

                continue;
            }
            if ($ch === ')' || $ch === ']' || $ch === '}') {
                $depth--;
                $current .= $ch;

                continue;
            }
            if ($ch === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }
            $current .= $ch;
        }
        if (trim($current) !== '' || $parts !== []) {
            $parts[] = $current;
        }

        return $parts;
    }

    private static function matchingParen(string $src, int $open): ?int
    {
        $depth = 0;
        $quote = null;
        $len = strlen($src);
        for ($i = $open; $i < $len; $i++) {
            $ch = $src[$i];
            if ($quote !== null) {
                if ($ch === '\\' && $i + 1 < $len) {
                    $i++;

                    continue;
                }
                if ($ch === $quote) {
                    $quote = null;
                }

                continue;
            }
            if ($ch === "'" || $ch === '"') {
                $quote = $ch;

                continue;
            }
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }
}
