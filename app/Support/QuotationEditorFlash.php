<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Save/publish flash for quotation editors: toast copy plus compact breadcrumb status.
 */
class QuotationEditorFlash
{
    public static function isEditorRequest(?Request $request = null): bool
    {
        $request = $request ?: request();
        if (! $request) {
            return false;
        }

        return $request->routeIs('admin.quotations.*.create', 'admin.quotations.*.edit')
            && ! $request->routeIs('admin.quotations.shared-prices.*');
    }

    /**
     * @return array{type: string, message: string, compact: string}
     */
    public static function current($errors = null): array
    {
        if (is_object($errors) && method_exists($errors, 'any') && $errors->any()) {
            $all = method_exists($errors, 'all') ? $errors->all() : [];
            $all = is_array($all) ? $all : [];
            $first = $all !== [] ? trim((string) reset($all)) : '';

            return [
                'type' => 'error',
                'message' => $first !== '' ? $first : 'Please fix the highlighted fields',
                'compact' => 'Please fix the highlighted fields',
            ];
        }

        $error = trim((string) session('error', ''));
        if ($error !== '') {
            return [
                'type' => 'error',
                'message' => $error,
                'compact' => self::compact($error, 'error'),
            ];
        }

        $warning = trim((string) session('warning', ''));
        if ($warning !== '') {
            return [
                'type' => 'warning',
                'message' => $warning,
                'compact' => self::compact($warning, 'warning'),
            ];
        }

        $success = trim((string) session('success', ''));
        if ($success !== '') {
            return [
                'type' => 'success',
                'message' => $success,
                'compact' => self::compact($success, 'success'),
            ];
        }

        return [
            'type' => 'success',
            'message' => '',
            'compact' => '',
        ];
    }

    public static function compact(?string $message, string $type = 'success'): string
    {
        $text = trim((string) $message);
        if ($text === '') {
            return $type === 'success' ? 'Saved' : 'Could not save';
        }
        if ($type !== 'success') {
            return $text;
        }

        $lower = mb_strtolower($text);
        if (str_contains($lower, 'saved as a draft') || str_contains($lower, 'saved as draft')) {
            return 'Saved as a draft';
        }
        if (str_contains($lower, 'linked to this project') || str_contains($lower, 'saved and linked') || str_contains($lower, 'published and linked') || str_contains($lower, 'updated and linked')) {
            return 'Saved and linked to this project';
        }
        if (preg_match('/\b(updated|saved)\b/i', $text)) {
            return 'Saved';
        }

        return rtrim($text, '.');
    }

    public static function icon(string $type): string
    {
        return match ($type) {
            'warning' => 'fa-exclamation-triangle',
            'error' => 'fa-exclamation-circle',
            default => 'fa-check-circle',
        };
    }
}
