<?php

namespace App\Logging;

use Monolog\Processor\ProcessorInterface;

/**
 * Masks passwords, tokens, phone numbers, and other sensitive values before logs are written.
 */
final class RedactionProcessor implements ProcessorInterface
{
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'token',
        'secret',
        'authorization',
        'api_key',
        'apikey',
        'api_token',
        'cookie',
        'credit_card',
        'cvv',
        'ssn',
        'sin',
        'mocean-text',
        'mocean-to',
        'user_email',
        'client_email',
        'email',
        'phone',
        'mobile',
    ];

    /** @var array<string, string> */
    private const STRING_PATTERNS = [
        '/Bearer\s+\S+/i' => 'Bearer [REDACTED]',
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/' => '[REDACTED_EMAIL]',
        '/\b\d{13,19}\b/' => '[REDACTED_CARD]',
    ];

    public function __invoke(array $record): array
    {
        $record['message'] = $this->redactString((string) ($record['message'] ?? ''));
        $record['context'] = $this->redactValue($record['context'] ?? []);
        $record['extra'] = $this->redactValue($record['extra'] ?? []);

        return $record;
    }

    private function redactValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->redactString($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $item) {
            if ($this->isSensitiveKey((string) $key)) {
                $out[$key] = '[REDACTED]';

                continue;
            }

            $out[$key] = $this->redactValue($item);
        }

        return $out;
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($lower, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function redactString(string $value): string
    {
        foreach (self::STRING_PATTERNS as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value) ?? $value;
        }

        return $value;
    }
}
