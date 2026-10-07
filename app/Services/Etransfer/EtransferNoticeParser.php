<?php

namespace App\Services\Etransfer;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Pulls sender, amount, Interac reference, memo, and date from a
 * notification email body (English or French; "X sent you money" and
 * auto-deposit "Your funds have been deposited" styles).
 */
class EtransferNoticeParser
{
    /** @var list<string> */
    private const INTERAC_EMAIL_DOMAINS = [
        'interac.ca',
        'payments.interac.ca',
        'notify.payments.interac.ca',
    ];

    /**
     * @return array{
     *   sender_name: ?string,
     *   sender_email: ?string,
     *   amount: ?float,
     *   currency: string,
     *   reference_number: ?string,
     *   message: ?string,
     *   received_at: ?CarbonInterface
     * }
     */
    public function parse(string $rawText): array
    {
        $text = $this->normalize($rawText);

        return [
            'sender_name' => $this->extractSenderName($text),
            'sender_email' => $this->extractSenderEmail($text),
            'amount' => $this->extractAmount($text),
            'currency' => 'CAD',
            'reference_number' => $this->extractReference($text),
            'message' => $this->extractMessage($text),
            'received_at' => $this->extractDate($text),
        ];
    }

    private function normalize(string $rawText): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $rawText);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function extractSenderName(string $text): ?string
    {
        $patterns = [
            '/^(.+?) sent you (?:money|\$)/imu',
            '/^(.+?) vous a envoyé/imu',
            '/from (.+?) has been automatically deposited/iu',
            '/de (.+?) ont été déposés/iu',
            '/Sender(?:\'s)? name\s*:\s*(.+)$/imu',
            '/Nom de l\'expéditeur\s*:\s*(.+)$/imu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $name = $this->cleanPersonName($m[1]);
                if ($name !== null) {
                    return $name;
                }
            }
        }

        return null;
    }

    private function cleanPersonName(string $raw): ?string
    {
        $name = trim($raw);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = trim($name, " \t\"'");

        if ($name === '' || mb_strlen($name) > 120) {
            return null;
        }

        $lower = mb_strtolower($name);
        if (str_contains($lower, 'interac') || str_contains($lower, 'e-transfer') || str_contains($lower, 'virement')) {
            return null;
        }

        return $name;
    }

    private function extractSenderEmail(string $text): ?string
    {
        if (preg_match('/Sender(?:\'s)? e-?mail\s*:\s*([^\s<>]+@[^\s<>]+)/iu', $text, $m)) {
            return $this->usableSenderEmail($m[1]);
        }

        if (preg_match('/Courriel de l\'expéditeur\s*:\s*([^\s<>]+@[^\s<>]+)/iu', $text, $m)) {
            return $this->usableSenderEmail($m[1]);
        }

        if (preg_match_all('/\b([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})\b/i', $text, $matches)) {
            foreach ($matches[1] as $email) {
                $usable = $this->usableSenderEmail($email);
                if ($usable !== null) {
                    return $usable;
                }
            }
        }

        return null;
    }

    private function usableSenderEmail(string $email): ?string
    {
        $email = strtolower(trim($email, " \t\"'<>"));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $domain = substr(strrchr($email, '@') ?: '', 1);
        if (in_array($domain, self::INTERAC_EMAIL_DOMAINS, true)) {
            return null;
        }

        return $email;
    }

    private function extractAmount(string $text): ?float
    {
        $contextual = [
            '/sent you\s+([0-9\s\.,]+)\s*\$/iu',
            '/sent you\s+\$\s*([0-9,]+\.\d{2})/iu',
            '/vous a envoyé\s+([0-9\s\.,]+)\s*\$/iu',
            '/\$\s*([0-9,]+\.\d{2})\s*(?:CAD|CDN)?\s+from /iu',
            '/([0-9\s\.,]+)\s*\$\s*(?:CA|CAD|CDN)?\s+de /iu',
            '/Amount\s*:\s*\$?\s*([0-9,]+\.\d{2})/iu',
            '/Montant\s*:\s*([0-9\s\.,]+)\s*\$/iu',
        ];

        foreach ($contextual as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $amount = $this->parseMoneyToken($m[1]);
                if ($amount !== null && $amount > 0) {
                    return $amount;
                }
            }
        }

        if (preg_match_all('/\$\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.\d{2})|[0-9]+\.\d{2})/u', $text, $en)) {
            foreach ($en[1] as $token) {
                $amount = $this->parseMoneyToken($token);
                if ($amount !== null && $amount > 0) {
                    return $amount;
                }
            }
        }

        if (preg_match_all('/([0-9]{1,3}(?:\s[0-9]{3})+,[0-9]{2}|[0-9]+,[0-9]{2})\s*\$/u', $text, $fr)) {
            foreach ($fr[1] as $token) {
                $amount = $this->parseMoneyToken($token);
                if ($amount !== null && $amount > 0) {
                    return $amount;
                }
            }
        }

        return null;
    }

    private function parseMoneyToken(string $raw): ?float
    {
        $token = trim($raw);
        if ($token === '') {
            return null;
        }

        $hasCommaDecimal = preg_match('/,\d{2}$/', $token) === 1;
        if ($hasCommaDecimal) {
            $token = str_replace([' ', '.'], '', $token);
            $token = str_replace(',', '.', $token);
        } else {
            $token = str_replace([' ', ','], '', $token);
        }

        if (! is_numeric($token)) {
            return null;
        }

        return round((float) $token, 2);
    }

    private function extractReference(string $text): ?string
    {
        $patterns = [
            '/Reference Number\s*:\s*([A-Z0-9][A-Z0-9\-]{4,})/iu',
            '/Confirmation Number\s*:\s*([A-Z0-9][A-Z0-9\-]{4,})/iu',
            '/Numéro de référence\s*:\s*([A-Z0-9][A-Z0-9\-]{4,})/iu',
            '/Numéro de confirmation\s*:\s*([A-Z0-9][A-Z0-9\-]{4,})/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return strtoupper(trim($m[1]));
            }
        }

        return null;
    }

    private function extractMessage(string $text): ?string
    {
        if (! preg_match('/^(?:Message|Memo|Note)\s*:\s*(.+)$/imu', $text, $m)) {
            return null;
        }

        $message = trim($m[1]);
        $message = trim($message, " \t\"'");
        $blank = ['', '-', 'n/a', 'na', 'none', 'aucun', 'aucune', '(none)', '(aucun)'];
        if (in_array(mb_strtolower($message), $blank, true)) {
            return null;
        }

        if (mb_strlen($message) > 500) {
            $message = mb_substr($message, 0, 500);
        }

        return $message;
    }

    private function extractDate(string $text): ?CarbonInterface
    {
        if (preg_match('/^(?:Sent|Date|Envoyé|Déposé)\s*:\s*(.+)$/imu', $text, $m)) {
            $parsed = $this->parseDateLine(trim($m[1]));
            if ($parsed !== null) {
                return $parsed;
            }
        }

        if (preg_match('/\b((?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},\s+\d{4}(?:\s+at\s+\d{1,2}:\d{2}\s*(?:AM|PM)?)?)/i', $text, $m)) {
            $parsed = $this->parseDateLine($m[1]);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        if (preg_match('/\b(\d{1,2}\s+(?:janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre)\s+\d{4}(?:\s+à\s+\d{1,2}\s*h\s*\d{2})?)/iu', $text, $m)) {
            $parsed = $this->parseDateLine($m[1]);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    private function parseDateLine(string $line): ?CarbonInterface
    {
        $line = trim($line);
        $line = preg_replace('/\s+(?:ET|EST|EDT|HE|HNE|HAE)\b/u', '', $line) ?? $line;
        $line = preg_replace('/\s+à\s+/u', ' ', $line) ?? $line;
        $line = preg_replace('/\s+at\s+/i', ' ', $line) ?? $line;
        $line = str_replace([' h ', 'h'], ':', $line);
        $line = $this->translateFrenchMonths($line);

        try {
            $tz = (string) config('app.timezone', 'America/Toronto');
            $parsed = Carbon::parse($line, $tz);
            if ($parsed->year < 2000 || $parsed->year > 2100) {
                return null;
            }

            return $parsed;
        } catch (\Throwable) {
            return null;
        }
    }

    private function translateFrenchMonths(string $line): string
    {
        $map = [
            'janvier' => 'January',
            'février' => 'February',
            'fevrier' => 'February',
            'mars' => 'March',
            'avril' => 'April',
            'mai' => 'May',
            'juin' => 'June',
            'juillet' => 'July',
            'août' => 'August',
            'aout' => 'August',
            'septembre' => 'September',
            'octobre' => 'October',
            'novembre' => 'November',
            'décembre' => 'December',
            'decembre' => 'December',
        ];

        return preg_replace_callback(
            '/\b('.implode('|', array_keys($map)).')\b/iu',
            function (array $m) use ($map) {
                $key = mb_strtolower($this->stripAccents($m[1]));
                foreach ($map as $fr => $en) {
                    if ($this->stripAccents($fr) === $key) {
                        return $en;
                    }
                }

                return $m[1];
            },
            $line
        ) ?? $line;
    }

    private function stripAccents(string $value): string
    {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return is_string($converted) ? strtolower($converted) : strtolower($value);
    }
}
