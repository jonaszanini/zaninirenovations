<?php

namespace App\Support;

/**
 * Plain-language errors for standard quotation job and material fields.
 * Laravel's defaults print paths like works.0.materials.0.description.
 */
class StandardQuotationValidationMessages
{
    /**
     * @param  mixed  $works
     * @return array<string, string>
     */
    public static function forWorks(mixed $works): array
    {
        if (! is_array($works)) {
            return [];
        }

        $messages = [];
        $jobNumber = 0;

        foreach ($works as $workKey => $work) {
            if (! is_array($work)) {
                continue;
            }

            $jobNumber++;
            $job = self::jobLabel($jobNumber, $work);
            $messages['works.'.$workKey.'.title.max'] = $job.': the job name is longer than 160 characters. Shorten it and save again.';

            $materials = $work['materials'] ?? null;
            if (! is_array($materials)) {
                continue;
            }

            $lineNumber = 0;
            $lineCount = count(array_filter($materials, 'is_array'));

            foreach ($materials as $matKey => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $lineNumber++;
                $where = self::materialWhere($job, $lineNumber, $lineCount, $row);
                $prefix = 'works.'.$workKey.'.materials.'.$matKey;
                $descLen = mb_strlen(trim((string) ($row['description'] ?? '')));

                $messages[$prefix.'.description.max'] = $where.' the Description is '
                    .$descLen.' characters. Shorten that Description field to 500 characters or fewer, then save again.';
                $messages[$prefix.'.short_name.max'] = $where.' the Short name is longer than 60 characters. Shorten it and save again.';
                $messages[$prefix.'.url.max'] = $where.' the Product URL is longer than 2,000 characters. Shorten it and save again.';
                $messages[$prefix.'.image_url.max'] = $where.' the Image URL is longer than 2,000 characters. Shorten it and save again.';
                $messages[$prefix.'.unit_price.numeric'] = $where.' enter a unit price as a number.';
                $messages[$prefix.'.unit_price.min'] = $where.' the unit price cannot be negative.';
                $messages[$prefix.'.quantity.numeric'] = $where.' enter a quantity as a number.';
                $messages[$prefix.'.quantity.min'] = $where.' the quantity cannot be negative.';
                $messages[$prefix.'.delivery_cad.numeric'] = $where.' delivery must be a number.';
                $messages[$prefix.'.delivery_cad.min'] = $where.' delivery cannot be negative.';
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $work
     */
    private static function jobLabel(int $number, array $work): string
    {
        $title = self::clip(trim((string) ($work['title'] ?? '')));
        if ($title === '') {
            return 'Job '.$number;
        }

        return 'Job '.$number.' ('.$title.')';
    }

    /**
     * Names the Materials section, the job, and the row so this is not confused
     * with "Description of the work" at the top of the form.
     *
     * @param  array<string, mixed>  $row
     */
    private static function materialWhere(string $job, int $lineNumber, int $lineCount, array $row): string
    {
        $place = 'Under Materials on '.$job;
        if ($lineCount > 1) {
            $place .= ', row '.$lineNumber;
        }

        $name = self::rowName($row);
        if ($name !== '') {
            $place .= ' ("'.$name.'")';
        }

        return $place.':';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function rowName(array $row): string
    {
        $short = self::clip(trim((string) ($row['short_name'] ?? '')));
        if ($short !== '') {
            return $short;
        }

        return self::clip(trim((string) ($row['description'] ?? '')));
    }

    private static function clip(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        if (mb_strlen($value) <= 40) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, 40)).'...';
    }
}
