<?php

namespace Tests\Support;

trait ExtractsPdfText
{
    /**
     * Decode visible strings from a generated DomPDF file (UTF-16BE Tj/TJ literals).
     */
    protected function extractPdfText(string $path): string
    {
        $bin = (string) file_get_contents($path);
        $chunks = [];

        $pdftotext = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
        if ($pdftotext !== '') {
            $txtPath = $path.'.txt';
            shell_exec(escapeshellarg($pdftotext).' -layout '.escapeshellarg($path).' '.escapeshellarg($txtPath).' 2>/dev/null');
            if (is_file($txtPath)) {
                $fromCmd = (string) file_get_contents($txtPath);
                if (trim($fromCmd) !== '') {
                    $chunks[] = $fromCmd;
                }
            }
        }

        // Use /Length so Flate payloads that happen to contain "endstream" bytes
        // are not truncated (that drop is how first-page CA$ totals go missing).
        if (preg_match_all('/\/Length\s+(\d+)\s*>>\s*stream\r?\n/', $bin, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $i => $full) {
                $length = (int) $matches[1][$i][0];
                $payload = substr($bin, $full[1] + strlen($full[0]), $length);
                $inflated = @gzuncompress($payload);
                if ($inflated === false) {
                    $inflated = @gzinflate($payload);
                }
                if (! is_string($inflated) || $inflated === '') {
                    $inflated = $payload;
                }
                if (! str_contains($inflated, 'Tj') && ! str_contains($inflated, 'TJ')) {
                    continue;
                }
                $chunks[] = $this->decodePdfStringLiterals($inflated);
            }
        }

        return implode("\n", $chunks);
    }

    /**
     * Strip NULs and whitespace so DomPDF glyph splits still match CA$1,234.56.
     */
    protected function pdfPlainText(string $text): string
    {
        $text = str_replace("\0", '', $text);

        return preg_replace('/\s+/u', '', $text) ?? $text;
    }

    protected function decodePdfStringLiterals(string $stream): string
    {
        if (! preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/', $stream, $matches)) {
            return '';
        }
        $out = '';
        foreach ($matches[0] as $literal) {
            $raw = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], substr($literal, 1, -1));
            if (str_starts_with($raw, "\xFE\xFF")) {
                $raw = substr($raw, 2);
            }
            $out .= str_contains($raw, "\x00")
                ? $this->utf16BeToUtf8($raw)
                : $raw;
        }

        return $out;
    }

    protected function utf16BeToUtf8(string $raw): string
    {
        $out = '';
        $len = strlen($raw);
        for ($i = 0; $i + 1 < $len; $i += 2) {
            $code = (ord($raw[$i]) << 8) | ord($raw[$i + 1]);
            if ($code === 0) {
                continue;
            }
            if ($code < 128) {
                $out .= chr($code);

                continue;
            }
            $out .= html_entity_decode('&#'.$code.';', ENT_QUOTES, 'UTF-8');
        }

        return $out;
    }
}
