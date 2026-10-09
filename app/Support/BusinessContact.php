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

        return $phone !== '' ? $phone : self::DEFAULT_PHONE;
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
