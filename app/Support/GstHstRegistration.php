<?php

namespace App\Support;

class GstHstRegistration
{
    /**
     * Normalized registration number (no spaces), e.g. 709216154RT0001.
     */
    public static function number(): ?string
    {
        $raw = strtoupper(preg_replace('/\s+/', '', trim((string) config('client_projects.gst_hst_registration_number', ''))));

        return $raw !== '' ? $raw : null;
    }

    /**
     * Display form for invoices, e.g. 70921 6154 RT0001.
     */
    public static function displayLabel(): ?string
    {
        $number = self::number();
        if ($number === null) {
            return null;
        }

        if (preg_match('/^(\d{5})(\d{4})(RT\d{4})$/', $number, $matches)) {
            return $matches[1].' '.$matches[2].' '.$matches[3];
        }

        return $number;
    }
}
