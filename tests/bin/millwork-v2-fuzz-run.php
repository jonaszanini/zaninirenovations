<?php

/**
 * Run saved-v2 calculate() snapshots for a JSON case list.
 * Usage: php tests/bin/millwork-v2-fuzz-run.php --base=/path --cases=cases.json
 */

$base = getcwd();
$casesPath = null;
foreach (array_slice($argv, 1) as $i => $arg) {
    if (str_starts_with($arg, '--base=')) {
        $base = substr($arg, 7);
    } elseif (str_starts_with($arg, '--cases=')) {
        $casesPath = substr($arg, 8);
    }
}
if ($casesPath === null) {
    fwrite(STDERR, "Missing --cases=\n");
    exit(2);
}

$loader = require $base.'/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($base): void {
    if (! str_starts_with($class, 'App\\')) {
        return;
    }
    $path = $base.'/app/'.str_replace('\\', '/', substr($class, 4)).'.php';
    if (is_file($path)) {
        require $path;
    }
}, true, true);

use App\Models\MillworkQuotation;
use App\Models\MillworkSettings;
use App\Services\MillworkQuotationCalculator;

$cases = json_decode((string) file_get_contents($casesPath), true);
if (! is_array($cases)) {
    fwrite(STDERR, "Bad cases JSON\n");
    exit(2);
}

$settings = new MillworkSettings();
$settings->owner_hourly_rate = 85;
$settings->helper_hourly_rate = 45;
$settings->hours_per_day = 8;
$settings->productivity = MillworkSettings::defaultProductivity();
$settings->catalog = MillworkSettings::defaultCatalog();
$settings->cut_settings = MillworkSettings::defaultCutSettings();

$out = [];
foreach ($cases as $id => $inputs) {
    $calc = (new MillworkQuotationCalculator)->calculate($inputs, $settings, 2);
    $quote = new MillworkQuotation([
        'cut_plan_version' => 2,
        'inputs' => $inputs,
        'calculation' => $calc,
    ]);
    $out[$id] = [
        'grand_total' => round((float) ($calc['summary']['grand_total'] ?? 0), 2),
        'labor_pre_hst' => round((float) ($calc['summary']['labor_pre_hst'] ?? 0), 2),
        'materials_pre_hst' => round((float) ($calc['summary']['materials_pre_hst'] ?? 0), 2),
        'preview_skipped' => (bool) ($calc['cut_plan']['preview_skipped'] ?? false),
        'labels' => $calc['scope']['labels'] ?? [],
        'pdf' => $quote->scopeDescriptionForPdf(),
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
