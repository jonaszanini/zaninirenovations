<?php

namespace App\Services;

use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use ZipArchive;

class RoomAreaMeasurementExport
{
    public const COLUMNS = [
        'Phase',
        'Room',
        'Area (sq ft)',
        'Perimeter (lf)',
        'Net wall (sq ft)',
        'Flooring with waste (sq ft)',
        'Casing (lf)',
        'Ceiling height (ft)',
        'Flooring waste (%)',
        'Flooring boxes',
        'Project',
        'Client',
    ];

    private RoomAreaPhaseTotals $totals;

    public function __construct(?RoomAreaPhaseTotals $totals = null)
    {
        $this->totals = $totals ?? new RoomAreaPhaseTotals;
    }

    /**
     * @return list<list<string|int|float>>
     */
    public function rows(RoomAreaProject $project): array
    {
        $project->loadMissing(['phases', 'rooms', 'clientProject']);
        $grouped = $this->totals->grouped($project);
        $client = (string) ($project->clientProject?->client_name ?: $project->clientProject?->name ?: '');
        $rows = [];
        $sections = $grouped['phases'];
        if ($grouped['unassigned']['rooms']->isNotEmpty() || $sections === []) {
            $sections[] = $grouped['unassigned'];
        }
        foreach ($sections as $section) {
            $allocated = $section['room_metrics'] ?? [];
            foreach ($section['rooms'] as $index => $room) {
                $rows[] = $this->roomRow(
                    $project,
                    $room,
                    (string) $section['name'],
                    $client,
                    $allocated[$index] ?? null
                );
            }
        }

        return $rows;
    }

    public const UTF8_BOM = "\xEF\xBB\xBF";

    public function csv(RoomAreaProject $project): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $this->sanitizeRow(self::COLUMNS));
        foreach ($this->rows($project) as $row) {
            fputcsv($handle, $this->sanitizeRow($row));
        }
        foreach ($this->totalRows($project) as $row) {
            fputcsv($handle, $this->sanitizeRow($row));
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return self::UTF8_BOM.$csv;
    }

    public function xlsx(RoomAreaProject $project): string
    {
        $sheetRows = [self::COLUMNS];
        foreach ($this->rows($project) as $row) {
            $sheetRows[] = $this->excelRow($row);
        }
        foreach ($this->totalRows($project) as $row) {
            $sheetRows[] = $this->excelRow($row);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ra-xlsx-');
        if ($tmp === false) {
            throw new \RuntimeException('Could not create a temporary Excel file.');
        }
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Could not write the Excel workbook.');
        }
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($sheetRows));
        $zip->close();
        $binary = file_get_contents($tmp) ?: '';
        @unlink($tmp);

        return $binary;
    }

    /**
     * @return list<list<string|int|float>>
     */
    private function totalRows(RoomAreaProject $project): array
    {
        $grouped = $this->totals->grouped($project);
        $rows = [[], ['Phase totals', '', '', '', '', '', '', '', '', '', '', '']];
        foreach ($grouped['phases'] as $phase) {
            $rows[] = $this->totalsRow((string) $phase['name'], $phase['totals']);
        }
        if ($grouped['unassigned']['rooms']->isNotEmpty()) {
            $rows[] = $this->totalsRow((string) $grouped['unassigned']['name'], $grouped['unassigned']['totals']);
        }
        $rows[] = $this->totalsRow('Project total', $grouped['project']);

        return $rows;
    }

    /**
     * @param  array<string, float|int>  $totals
     * @return list<string|int|float>
     */
    private function totalsRow(string $label, array $totals): array
    {
        return [
            $label,
            '',
            $totals['area_sqft'] ?? 0,
            $totals['perimeter_lf'] ?? 0,
            $totals['net_wall_area_sqft'] ?? 0,
            $totals['flooring_with_waste_sqft'] ?? 0,
            $totals['casing_lf'] ?? 0,
            '',
            '',
            '',
            '',
            '',
        ];
    }

    /**
     * Prefix formula-like values so spreadsheet apps keep them as text.
     *
     * @param  string|int|float|null  $value
     */
    public function sanitizeCell($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_int($value) || is_float($value)) {
            return number_format(round((float) $value, 2), 2, '.', '');
        }
        $value = (string) $value;
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  list<string|int|float|null>  $row
     * @return list<string>
     */
    public function sanitizeRow(array $row): array
    {
        $out = [];
        foreach ($row as $cell) {
            $out[] = $this->sanitizeCell($cell);
        }

        return $out;
    }

    /**
     * Keep numbers numeric (including negatives) and text as plain strings.
     * Formula-looking text is NOT apostrophe-prefixed here — CSV keeps that guard.
     *
     * @param  list<string|int|float|null>  $row
     * @return list<string|int|float>
     */
    public function excelRow(array $row): array
    {
        $out = [];
        foreach ($row as $cell) {
            if ($cell === null || $cell === '') {
                $out[] = '';
                continue;
            }
            if (is_int($cell) || is_float($cell)) {
                $out[] = is_int($cell) ? $cell : round((float) $cell, 2);
                continue;
            }
            $out[] = (string) $cell;
        }

        return $out;
    }

    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>';
        foreach ($rows as $r => $row) {
            $xml .= '<row r="'.($r + 1).'">';
            foreach ($row as $c => $value) {
                $ref = $this->cellRef($c, $r + 1);
                if ($value === '' || $value === null) {
                    $xml .= '<c r="'.$ref.'"/>';
                    continue;
                }
                if (is_int($value) || is_float($value)) {
                    $numeric = is_int($value) ? (string) $value : number_format((float) $value, 2, '.', '');
                    $xml .= '<c r="'.$ref.'" t="n"><v>'.$numeric.'</v></c>';
                    continue;
                }
                $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.$this->xml((string) $value).'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    /**
     * @param  array<string, float|int>|null  $allocated
     * @return list<string|int|float>
     */
    private function roomRow(RoomAreaProject $project, RoomAreaRoom $room, string $phaseName, string $client, ?array $allocated = null): array
    {
        $metrics = $allocated ?? $room->layoutMetrics();

        return [
            $phaseName,
            (string) $room->name,
            round((float) ($metrics['area_sqft'] ?? 0), 2),
            round((float) ($metrics['perimeter_lf'] ?? 0), 2),
            round((float) ($metrics['net_wall_area_sqft'] ?? 0), 2),
            round((float) ($metrics['flooring_with_waste_sqft'] ?? 0), 2),
            round((float) ($metrics['casing_lf'] ?? 0), 2),
            round((float) ($metrics['ceiling_height_ft'] ?? 0), 2),
            round((float) ($metrics['flooring_waste_percent'] ?? 0), 2),
            (int) ($metrics['flooring_boxes'] ?? 0),
            (string) $project->name,
            $client,
        ];
    }

    private function cellRef(int $col, int $row): string
    {
        $name = '';
        $n = $col;
        do {
            $name = chr(65 + ($n % 26)).$name;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $name.$row;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Rooms" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }
}
