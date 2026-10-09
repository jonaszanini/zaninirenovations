<?php

namespace App\Support;

/**
 * Configured public contact details (phone/email) for client-facing pages.
 */
class BusinessContact
{
    public static function name(): string
    {
        $name = trim((string) config('mail.from.name', ''));

        return $name !== '' ? $name : 'Zanini Renovations';
    }

    public static function phoneDisplay(): string
    {
        $phone = trim((string) config('blog.phone_display', ''));
        if ($phone !== '') {
            return $phone;
        }
        $label = trim((string) config('public_services.cta.phone_label', ''));
        if ($label !== '' && preg_match('/\(?\d/', $label)) {
            return trim((string) preg_replace('/^Call\s+/i', '', $label));
        }

        return '';
    }

    public static function phoneHref(): string
    {
        $href = trim((string) config('blog.phone_tel', ''));
        if ($href !== '') {
            return str_starts_with($href, 'tel:') ? $href : 'tel:'.$href;
        }
        $cta = trim((string) config('public_services.cta.phone_href', ''));

        return $cta !== '' ? $cta : '';
    }

    public static function email(): string
    {
        $email = trim((string) config('mail.from.address', ''));
        if ($email !== '') {
            return $email;
        }

        return trim((string) config('public_services.cta.email_label', ''));
    }
}
