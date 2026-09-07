<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\ClientProjectDocumentLink;

class ClientProjectDocumentLinkService
{
    public const KIND_AUTO = 'auto';

    /** @var list<string> */
    private const WORD_EXTENSIONS = ['doc', 'docx', 'docm', 'dot', 'dotx', 'odt', 'rtf'];

    /** @var list<string> */
    private const EXCEL_EXTENSIONS = ['xls', 'xlsx', 'xlsm', 'xlsb', 'csv', 'ods'];

    /** @var list<string> */
    private const PPT_EXTENSIONS = ['ppt', 'pptx', 'pptm', 'pps', 'ppsx', 'odp'];

    /**
     * @param  mixed  $rows  Request `document_links` array
     */
    public function storeFromRequest(ClientProject $project, mixed $rows): int
    {
        if (! is_array($rows)) {
            return 0;
        }

        $startOrder = (int) $project->documentLinks()->max('sort_order');
        $added = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $url = self::normalizeUrl((string) ($row['url'] ?? ''));
            if ($url === null) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $kindOverride = strtolower(trim((string) ($row['kind'] ?? self::KIND_AUTO)));

            $project->documentLinks()->create([
                'url' => $url,
                'title' => $title !== '' ? $title : self::suggestTitle($url),
                'kind' => $this->detectKind($url, $kindOverride),
                'sort_order' => ++$startOrder,
            ]);
            $added++;
        }

        return $added;
    }

    public function detectKind(string $url, ?string $override = null): string
    {
        $override = strtolower(trim((string) $override));
        if (in_array($override, [
            ClientProjectDocumentLink::KIND_WORD,
            ClientProjectDocumentLink::KIND_EXCEL,
            ClientProjectDocumentLink::KIND_PPT,
        ], true)) {
            return $override;
        }

        $fromExtension = $this->kindFromExtension($url);
        if ($fromExtension !== null) {
            return $fromExtension;
        }

        $fromHost = $this->kindFromHostPatterns($url);
        if ($fromHost !== null) {
            return $fromHost;
        }

        return ClientProjectDocumentLink::KIND_OTHER;
    }

    public static function normalizeUrl(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
            $value = 'https://'.$value;
        }

        $parts = parse_url($value);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $value;
    }

    public static function suggestTitle(string $url): string
    {
        $query = [];
        parse_str((string) (parse_url($url, PHP_URL_QUERY) ?? ''), $query);
        $fromFileParam = trim(urldecode((string) ($query['file'] ?? '')));
        if ($fromFileParam !== '') {
            return $fromFileParam;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $base = urldecode(basename($path));
        $base = preg_replace('/[?#].*$/', '', $base) ?? $base;

        if ($base !== '' && $base !== '/' && ! str_contains($base, '.')) {
            // SharePoint / OneDrive share paths are opaque tokens — use the host.
            $base = '';
        }

        if ($base !== '' && $base !== '/' && ! preg_match('/^(doc|word|excel|powerpoint)\.aspx$/i', $base)) {
            return $base;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

        return $host !== '' ? $host : 'Document link';
    }

    private function kindFromExtension(string $url): ?string
    {
        $haystack = urldecode($url);
        if (! preg_match_all('/\.([a-z0-9]{2,5})(?:$|[?#&\\/])/i', $haystack, $matches)) {
            return null;
        }

        foreach (array_reverse($matches[1]) as $ext) {
            $ext = strtolower((string) $ext);
            if (in_array($ext, self::WORD_EXTENSIONS, true)) {
                return ClientProjectDocumentLink::KIND_WORD;
            }
            if (in_array($ext, self::EXCEL_EXTENSIONS, true)) {
                return ClientProjectDocumentLink::KIND_EXCEL;
            }
            if (in_array($ext, self::PPT_EXTENSIONS, true)) {
                return ClientProjectDocumentLink::KIND_PPT;
            }
        }

        return null;
    }

    private function kindFromHostPatterns(string $url): ?string
    {
        $lower = strtolower($url);
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));

        if (str_contains($host, 'docs.google.com') || str_contains($lower, 'docs.google.com')) {
            if (str_contains($lower, '/spreadsheets')) {
                return ClientProjectDocumentLink::KIND_EXCEL;
            }
            if (str_contains($lower, '/presentation')) {
                return ClientProjectDocumentLink::KIND_PPT;
            }
            if (str_contains($lower, '/document')) {
                return ClientProjectDocumentLink::KIND_WORD;
            }
        }

        // OneDrive short links: 1drv.ms/w/ … /x/ … /p/
        if (preg_match('#1drv\.ms/([wxp])/#', $lower, $m)) {
            return $this->kindFromMicrosoftMarker((string) $m[1]);
        }

        // SharePoint / OneDrive deep links: /:w:/ /:x:/ /:p:/
        if (preg_match('#/:([wxp]):/#', $lower, $m)) {
            return $this->kindFromMicrosoftMarker((string) $m[1]);
        }

        if (str_contains($path, 'powerpoint.aspx') || str_contains($lower, 'powerpoint.aspx')) {
            return ClientProjectDocumentLink::KIND_PPT;
        }
        if (str_contains($path, 'xlviewer') || str_contains($lower, 'excel.aspx')) {
            return ClientProjectDocumentLink::KIND_EXCEL;
        }
        if (str_contains($path, 'wordviewer') || str_contains($lower, 'word.aspx')) {
            return ClientProjectDocumentLink::KIND_WORD;
        }

        return null;
    }

    private function kindFromMicrosoftMarker(string $marker): string
    {
        return match ($marker) {
            'w' => ClientProjectDocumentLink::KIND_WORD,
            'x' => ClientProjectDocumentLink::KIND_EXCEL,
            'p' => ClientProjectDocumentLink::KIND_PPT,
            default => ClientProjectDocumentLink::KIND_OTHER,
        };
    }
}
