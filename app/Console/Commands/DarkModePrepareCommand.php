<?php

namespace App\Console\Commands;

use Database\Seeders\DarkModeAuditSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class DarkModePrepareCommand extends Command
{
    protected $signature = 'dark-mode:prepare
                            {--fresh : Run migrate:fresh before seeding}
                            {--path= : Output JSON path (default storage/app/dark-mode-audit-pages.json)}';

    protected $description = 'Seed dark-mode audit data and write the page list consumed by dark-mode-audit.js';

    /** @var list<string> */
    private const SKIP_NAME_FRAGMENTS = [
        'google-photos',
        'callback',
        'thumbnail',
        'picker-sessions',
        'pdf',
        'receipt',
        'purchase-list',
        'cut-list',
        'layout-diagrams',
        'vendor-link',
        'background',
        'file',
        'download',
        'live',
        'preview',
        'calculate',
        'search',
        'layouts',
        'travel-estimate',
        'duplicate-check',
        'picker-events',
        'material-photo',
        'nosing-option-photo',
    ];

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--force' => true]);
        }

        $seeder = new DarkModeAuditSeeder();
        $seeder->setCommand($this);
        $seeder->run();
        $ids = $seeder->ids;

        $pages = $this->pagesFromIds($ids);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'admin_email' => DarkModeAuditSeeder::ADMIN_EMAIL,
            'admin_password' => DarkModeAuditSeeder::ADMIN_PASSWORD,
            'ids' => $ids,
            'pages' => $pages,
        ];

        $path = $this->option('path') ?: storage_path('app/dark-mode-audit-pages.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->info('Wrote '.count($pages).' audit pages to '.$path);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $ids
     * @return list<array<string, mixed>>
     */
    private function pagesFromIds(array $ids): array
    {
        $paramMap = [
            'client' => $ids['client_id'],
            'client_project' => $ids['hourly_project_id'],
            'invoice' => $ids['invoice_id'],
            'quotation' => $ids['quotation_id'],
            'helper' => $ids['helper_id'],
            'vehicle' => $ids['vehicle_id'] ?? null,
            'material' => $ids['material_id'] ?? null,
            'project' => $ids['portfolio_project_id'] ?? null,
            'fence_quotation' => $ids['fence_quotation_id'],
            'low_deck_quotation' => $ids['low_deck_quotation_id'],
            'standard_quotation' => $ids['standard_quotation_id'],
            'paving_quotation' => $ids['paving_quotation_id'],
            'stairs_quotation' => $ids['stairs_quotation_id'],
            'painting_quotation' => $ids['painting_quotation_id'] ?? null,
            'flooring_quotation' => $ids['flooring_quotation_id'] ?? null,
            'tile_quotation' => $ids['tile_quotation_id'] ?? null,
            'basement_quotation' => $ids['basement_quotation_id'] ?? null,
            'shed_quotation' => $ids['shed_quotation_id'] ?? null,
            'millwork_quotation' => $ids['millwork_quotation_id'] ?? null,
            'trimming_quotation' => $ids['trimming_quotation_id'] ?? null,
            'post' => $ids['blog_post_id'],
            'category' => $ids['blog_category_id'],
            'user' => $ids['admin_user_id'] ?? 1,
            'ai_instruction' => 'contract',
        ];

        // Tile editor uses {quotation} not {tile_quotation}.
        $pages = [];
        $seen = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $name = (string) $route->getName();
            if ($name === '' || ! Str::startsWith($name, 'admin.')) {
                continue;
            }
            if ($this->shouldSkipName($name)) {
                continue;
            }

            $params = [];
            $skip = false;
            foreach ($route->parameterNames() as $param) {
                if ($param === 'quotation' && Str::contains($name, 'quotations.tile')) {
                    $params[$param] = $ids['tile_quotation_id'] ?? null;
                } elseif ($param === 'client_project' && Str::contains($name, 'invoices')) {
                    $params[$param] = $ids['hourly_project_id'];
                } elseif ($param === 'client_project' && Str::contains($name, 'quotation')) {
                    $params[$param] = $ids['fixed_project_id'];
                } elseif ($param === 'client_project' && Str::contains($name, 'contract')) {
                    $params[$param] = $ids['fixed_project_id'];
                } else {
                    $params[$param] = $paramMap[$param] ?? null;
                }
                if ($params[$param] === null) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            try {
                $url = route($name, $params, false);
            } catch (\Throwable) {
                continue;
            }

            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $pages[] = [
                'name' => $name,
                'url' => $url,
            ];
        }

        $hourlyShow = route('admin.client-projects.show', $ids['hourly_project_id'], false);
        $fixedShow = route('admin.client-projects.show', $ids['fixed_project_id'], false);

        foreach (['overview', 'schedule', 'time', 'quotation', 'contract', 'invoice', 'financials', 'utilities', 'history'] as $tab) {
            $pages[] = [
                'name' => 'admin.client-projects.show#'.$tab,
                'url' => $hourlyShow,
                'tab' => $tab,
            ];
        }

        $pages[] = [
            'name' => 'admin.client-projects.show#invoice-fixed',
            'url' => $fixedShow,
            'tab' => 'invoice',
        ];
        $pages[] = [
            'name' => 'ctrlk-palette',
            'url' => route('admin.client-projects.index', [], false),
            'openPalette' => true,
        ];
        $pages[] = [
            'name' => 'mark-paid-modal',
            'url' => $hourlyShow,
            'tab' => 'invoice',
            'openMarkPaid' => true,
        ];
        $pages[] = [
            'name' => 'quotation-actions-menu',
            'url' => $fixedShow,
            'tab' => 'quotation',
            'openQuotationMenu' => true,
        ];

        return $pages;
    }

    private function shouldSkipName(string $name): bool
    {
        foreach (self::SKIP_NAME_FRAGMENTS as $fragment) {
            if (Str::contains($name, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
