<?php

namespace App\Support;

use App\Models\CompanySetting;

/**
 * Public contact details from admin company settings.
 */
class BusinessContact
{
    public const DEFAULT_NAME = 'Zanini Renovations';
    public const DEFAULT_PHONE = '(647) 834-7570';
    public const DEFAULT_PHONE_TEL = '+16478347570';
    public const DEFAULT_EMAIL = 'zaninirenovations@gmail.com';

    public static function name(): string
    {
        return self::DEFAULT_NAME;
    }

    public static function phoneDisplay(): string
    {
        $phone = trim((string) (self::settings()?->contact_phone ?? ''));
        if ($phone === '') {
            $phone = self::DEFAULT_PHONE;
        }

        return self::normalizePhoneDisplay($phone);
    }

    /**
     * Canonical public format: "(647) 834-7570" (space after area code, not a hyphen).
     */
    public static function normalizePhoneDisplay(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 11 && $digits[0] === '1') {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
        }

        return str_replace('(647)-', '(647) ', $phone);
    }

    public static function phoneHref(): string
    {
        $href = trim((string) (self::settings()?->contact_phone_tel ?? ''));
        if ($href === '') {
            $href = self::DEFAULT_PHONE_TEL;
        }

        return str_starts_with($href, 'tel:') ? $href : 'tel:'.$href;
    }

    public static function email(): string
    {
        $email = trim((string) (self::settings()?->contact_email ?? ''));

        return $email !== '' ? $email : self::DEFAULT_EMAIL;
    }

    private static function settings(): ?CompanySetting
    {
        try {
            return CompanySetting::current();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
