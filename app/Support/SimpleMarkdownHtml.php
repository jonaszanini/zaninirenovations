<?php

namespace App\Support;

/**
 * Minimal Markdown to HTML for AI contract drafts (headings, lists, tables).
 */
class SimpleMarkdownHtml
{
    public static function convert(string $markdown): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
        $html = [];
        $i = 0;
        $count = count($lines);

        while ($i < $count) {
            $line = $lines[$i];

            if (self::isTableRow($line) && $i + 1 < $count && self::isTableSeparator($lines[$i + 1])) {
                [$tableHtml, $consumed] = self::parseTable(array_slice($lines, $i));
                $html[] = $tableHtml;
                $i += $consumed;

                continue;
            }

            if (preg_match('/^[-*]\s+/', $line)) {
                [$listHtml, $consumed] = self::parseList(array_slice($lines, $i));
                $html[] = $listHtml;
                $i += $consumed;

                continue;
            }

            if (trim($line) === '') {
                $i++;

                continue;
            }

            if (preg_match('/^\d+\.\s/', trim($line))) {
                $html[] = '<p><strong>'.self::inline(trim($line)).'</strong></p>';
            } else {
                $html[] = '<p>'.self::inline(trim($line)).'</p>';
            }

            $i++;
        }

        return implode("\n", $html);
    }

    private static function isTableRow(string $line): bool
    {
        $t = trim($line);

        return $t !== '' && str_contains($t, '|');
    }

    private static function isTableSeparator(string $line): bool
    {
        $t = trim($line);

        return (bool) preg_match('/^\|?[\s:-]+\|[\s|:-]+\|?$/', $t);
    }

    /**
     * @param  list<string>  $lines
     * @return array{0: string, 1: int}
     */
    private static function parseTable(array $lines): array
    {
        $rows = [];
        $i = 0;

        while ($i < count($lines) && self::isTableRow($lines[$i])) {
            if (self::isTableSeparator($lines[$i])) {
                $i++;

                continue;
            }

            $cells = array_map('trim', explode('|', trim($lines[$i], " \t|")));
            $cells = array_values(array_filter($cells, fn ($c) => $c !== ''));
            if ($cells !== []) {
                $rows[] = $cells;
            }
            $i++;
        }

        if ($rows === []) {
            return ['', max(1, $i)];
        }

        $header = array_shift($rows);
        $html = '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;margin:12px 0;">';
        $html .= '<thead><tr>';
        foreach ($header as $cell) {
            $html .= '<th style="text-align:left;">'.self::inline($cell).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>'.self::inline($cell).'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return [$html, $i];
    }

    /**
     * @param  list<string>  $lines
     * @return array{0: string, 1: int}
     */
    private static function parseList(array $lines): array
    {
        $items = [];
        $i = 0;

        while ($i < count($lines) && preg_match('/^[-*]\s+(.+)$/', $lines[$i], $m)) {
            $items[] = self::inline(trim($m[1]));
            $i++;
        }

        $html = '<ul>';
        foreach ($items as $item) {
            $html .= '<li>'.$item.'</li>';
        }
        $html .= '</ul>';

        return [$html, max(1, $i)];
    }

    private static function inline(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $escaped = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;

        return $escaped;
    }
}
