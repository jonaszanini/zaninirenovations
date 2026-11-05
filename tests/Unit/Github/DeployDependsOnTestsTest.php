<?php

namespace Tests\Unit\Github;

use Tests\TestCase;

class DeployDependsOnTestsTest extends TestCase
{
    public function test_tests_workflow_cancels_superseded_runs_for_the_ref(): void
    {
        $yaml = $this->workflow('tests.yml');

        $this->assertMatchesRegularExpression(
            '/^on:\s*$/m',
            $yaml
        );
        $this->assertMatchesRegularExpression(
            '/concurrency:\s*\n\s+group:\s+tests-\$\{\{\s*github\.workflow\s*\}\}-\$\{\{\s*github\.ref\s*\}\}\s*\n\s+cancel-in-progress:\s+\$\{\{\s*github\.ref\s*!=\s*[\'"]refs\/heads\/main[\'"]\s*\}\}/m',
            $yaml
        );
        $this->assertStringContainsString('dorny/paths-filter@v3', $yaml);
        $this->assertStringContainsString('tests/ci/classify-ci-changes.sh', $yaml);
        $this->assertStringContainsString('tests/ci/compute-ci-flags.sh', $yaml);
        $this->assertStringContainsString('tests/ci/run-js-browser-suite.sh', $yaml);
        $this->assertStringContainsString('tests/ci/run-js-unit-suite.sh', $yaml);
        $this->assertStringContainsString('global_test_deps:', $yaml);
        $this->assertStringContainsString('run_mysql_node', $yaml);
        $this->assertStringContainsString('RoomAreaEngineFeatureFlagTest.php', $yaml);
        $this->assertMatchesRegularExpression('/^\s+changes:\s*$/m', $yaml);
        $this->assertMatchesRegularExpression('/^\s+unit_js:\s*$/m', $yaml);
        $this->assertMatchesRegularExpression('/^\s+mysql:\s*$/m', $yaml);
        $this->assertStringContainsString('DOCS_ONLY', $yaml);
        $this->assertStringContainsString('run_database', $yaml);
        $this->assertStringContainsString("push:\n    branches:\n      - main", $yaml);
        $this->assertStringContainsString('pull_request:', $yaml);
        $this->assertMatchesRegularExpression(
            '/pull_request:\s*\n\s+types:\s*\[opened,\s*synchronize,\s*reopened,\s*ready_for_review\]/',
            $yaml
        );
        $this->assertStringContainsString('workflow_dispatch:', $yaml);
        $unitList = (string) file_get_contents(base_path('tests/ci/js-unit-paths.txt'));
        $this->assertStringContainsString(
            'openplan3d-editor-bridge.test.js',
            $unitList,
            'editor bridge unit test must stay in tests/ci/js-unit-paths.txt'
        );
        $browserLists = [
            'tests/ci/js-browser-openplan3d.txt',
            'tests/ci/js-browser-admin.txt',
            'tests/ci/js-browser-quotation.txt',
            'tests/ci/js-browser-client-projects.txt',
        ];
        $browserCombined = '';
        foreach ($browserLists as $rel) {
            $browserCombined .= (string) file_get_contents(base_path($rel));
        }
        $this->assertStringContainsString(
            'openplan3d-editor-browser.test.js',
            $browserCombined,
            'editor browser test must stay in tests/ci/js-browser-*.txt manifests'
        );
        $this->assertMatchesRegularExpression(
            '/fetch-depth:\s*0/',
            $yaml,
            'Shallow clone hides 86e68a3e from QuotationEditorControlInventoryTest'
        );
        $this->assertStringContainsString('tests/ci/run-database-suite.sh', $yaml);
        $this->assertStringContainsString('tests/ci/run-unit-suite.sh', $yaml);
        $this->assertDoesNotMatchRegularExpression(
            '/vendor\/bin\/phpunit[^\n]*"\$\{files\[@\]\}"/',
            $yaml,
            'PHPUnit 9 only runs the first CLI path; do not pass many files to one phpunit invocation'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/php artisan test \\\\\s*\n\s+tests\//',
            $yaml,
            'PHPUnit 9 only runs the first path; do not pass multiple files to one artisan test'
        );
    }

    public function test_unit_ci_suite_includes_every_listed_path_as_phpunit_file(): void
    {
        $unit = $this->pathsFile('tests/ci/unit-paths.txt');
        $this->assertGreaterThan(20, count($unit));

        $ciXml = sys_get_temp_dir().'/phpunit.ci-unit.'.uniqid('', true).'.xml';
        $script = base_path('tests/ci/build-phpunit-suite.sh');
        $listFile = sys_get_temp_dir().'/unit-paths.'.uniqid('', true).'.txt';
        file_put_contents($listFile, implode("\n", $unit)."\n");

        $cmd = 'bash '.escapeshellarg($script).' '.escapeshellarg($ciXml).' '.escapeshellarg($listFile).' 2>&1';
        $out = shell_exec($cmd);
        $this->assertFileExists($ciXml, $out ?? 'build-phpunit-suite.sh failed');

        $xml = file_get_contents($ciXml);
        $this->assertNotFalse($xml);
        preg_match_all('/<file>([^<]+)<\/file>/', $xml, $m);
        $listed = $m[1] ?? [];
        $this->assertSame(count($unit), count($listed), 'Each unit-paths.txt entry must become a <file> in the generated config');

        foreach ($unit as $path) {
            $this->assertContains($path, $listed, $path.' missing from generated PHPUnit suite');
        }

        @unlink($ciXml);
        @unlink($listFile);
    }

    public function test_classify_ci_changes_script_treats_md_only_diff_as_docs_only(): void
    {
        $script = base_path('tests/ci/classify-ci-changes.sh');
        $this->assertFileExists($script);

        $dir = sys_get_temp_dir().'/ci-classify-'.uniqid('', true);
        mkdir($dir);
        mkdir("$dir/docs");
        exec('git init -q '.escapeshellarg($dir));
        exec('git -C '.escapeshellarg($dir).' -c user.email=ci@test -c user.name=ci commit -q --allow-empty -m base');
        file_put_contents("$dir/docs/note.md", "# note\n");
        exec('git -C '.escapeshellarg($dir).' add docs/note.md');
        exec('git -C '.escapeshellarg($dir).' -c user.email=ci@test -c user.name=ci commit -q -m docs');

        $outFile = sys_get_temp_dir().'/ci-classify-out-'.uniqid('', true);
        chdir($dir);
        $cmd = 'GITHUB_OUTPUT='.escapeshellarg($outFile)
            .' GITHUB_EVENT_NAME=pull_request PR_BASE=HEAD~1 PR_HEAD=HEAD'
            .' bash '.escapeshellarg($script);
        exec($cmd.' 2>&1', $output, $code);
        chdir(base_path());

        $this->assertSame(0, $code, implode("\n", $output));
        $this->assertFileExists($outFile);
        $this->assertStringContainsString('docs_only=true', (string) file_get_contents($outFile));
        $this->assertStringContainsString('has_code_changes=false', (string) file_get_contents($outFile));

        @unlink($outFile);
        exec('rm -rf '.escapeshellarg($dir));
    }

    public function test_tests_workflow_lists_every_ci_database_and_unit_path_on_disk(): void
    {
        $unit = $this->pathsFile('tests/ci/unit-paths.txt');

        $this->assertSame(
            1,
            substr_count(implode("\n", $unit), 'tests/Unit/Github/DeployDependsOnTestsTest.php'),
            'DeployDependsOnTestsTest must appear once in unit-paths.txt'
        );
        $core = $this->pathsFile('tests/ci/database-core.txt');
        $extended = $this->pathsFile('tests/ci/database-extended.txt');

        $this->assertContains('tests/Unit/Github/DeployDependsOnTestsTest.php', $unit);
        $this->assertContains('tests/Unit/Services/OpenPlan3DPlanValidatorTest.php', $unit);
        $this->assertContains('tests/Unit/Services/FlooringQuotationCalculatorTest.php', $core);
        $this->assertContains('tests/Unit/Services/SharedWorkCatalogTest.php', $core);
        $this->assertNotContains('tests/Unit/Services/FlooringQuotationCalculatorTest.php', $unit);
        $this->assertNotContains('tests/Unit/Services/SharedWorkCatalogTest.php', $unit);
        $this->assertContains('tests/Feature/Admin/RoomAreaOpenPlan3DSaveTest.php', $extended);
        $this->assertContains('tests/Unit/Services/RoomAreaDrawingImporterTest.php', $extended);
        $this->assertContains('tests/Feature/Admin/RoomAreaCanvasObjectsTest.php', $extended);
        $this->assertContains('tests/Feature/SharedWorksNormalizedLabelMigrationTest.php', $core);
        $this->assertFileDoesNotExist(base_path('tests/Feature/PublicPageBannedWordsTest.php'));
        $this->assertFileDoesNotExist(base_path('tests/Unit/Views/HomeServiceTileUppercaseTest.php'));
        $this->assertFileDoesNotExist(base_path('tests/Feature/ExampleTest.php'));
        $this->assertGreaterThan(20, count($unit));
        $this->assertGreaterThan(10, count($core));
        $this->assertGreaterThan(20, count($extended));
        $this->assertSame(count($unit), count(array_unique($unit)));
        $this->assertSame(count($core), count(array_unique($core)));
        $this->assertSame(count($extended), count(array_unique($extended)));

        foreach (array_merge($unit, $core, $extended) as $path) {
            $full = base_path($path);
            $this->assertTrue(is_file($full) || is_dir($full), $path.' is listed but missing on disk');
        }
    }

    public function test_room_area_engine_feature_flag_runs_on_pr_when_room_area_filter_matches(): void
    {
        $roomAreaFilter = $this->pathsFile('tests/ci/database-filter-room-area.txt');
        $this->assertContains(
            'tests/Feature/Admin/RoomAreaEngineFeatureFlagTest.php',
            $roomAreaFilter,
            'OpenPlan3D manifest/vendor PRs must run RoomAreaEngineFeatureFlagTest via CI_MYSQL_FILTER_ROOM_AREA, not main-only extended.txt alone'
        );
    }

    public function test_ci_filter_matrix_script_passes(): void
    {
        exec('bash '.escapeshellarg(base_path('tests/ci/ci-filter-matrix.test.sh')).' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
    }

    public function test_workflow_filters_json_matches_tests_yml(): void
    {
        exec('node '.escapeshellarg(base_path('tests/ci/verify-workflow-filters.js')).' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
    }

    public function test_ci_js_browser_path_lists_cover_workflow_browser_tests(): void
    {
        $lists = [
            'tests/ci/js-browser-admin.txt',
            'tests/ci/js-browser-openplan3d.txt',
            'tests/ci/js-browser-quotation.txt',
            'tests/ci/js-browser-client-projects.txt',
        ];
        $paths = [];
        foreach ($lists as $list) {
            foreach ($this->pathsFile($list) as $path) {
                $paths[] = $path;
            }
        }
        $paths = array_values(array_unique($paths));
        $this->assertGreaterThan(10, count($paths));
        foreach ($paths as $path) {
            $this->assertFileExists(base_path($path), $path);
        }
    }

    public function test_deploy_requires_tests_success_and_is_skipped_when_tests_fail(): void
    {
        $yaml = $this->workflow('deploy.yml');
        $this->assertMatchesRegularExpression(
            '/concurrency:\s*\n\s+group:\s+production-deploy\s*\n\s+cancel-in-progress:\s+false/m',
            $yaml
        );
        $this->assertStringContainsString('require-tests:', $yaml);
        $this->assertStringContainsString('needs.require-tests.result', $yaml);
        $onBlock = $this->section($yaml, 'on:', 'concurrency:');

        $this->assertStringContainsString('workflow_run:', $onBlock);
        $this->assertMatchesRegularExpression('/workflows:\s*\n\s+-\s+Tests/', $onBlock);
        $this->assertStringContainsString('completed', $onBlock);
        $this->assertStringContainsString('workflow_dispatch:', $onBlock);
        $this->assertDoesNotMatchRegularExpression('/^\s+push:\s*$/m', $onBlock);

        $this->assertMatchesRegularExpression(
            '/conclusion\s*==\s*[\'"]success[\'"]/',
            $yaml
        );
        $this->assertMatchesRegularExpression(
            '/head_branch\s*==\s*[\'"]main[\'"]/',
            $yaml
        );
        $this->assertMatchesRegularExpression(
            '/workflow_run\.event\s*==\s*[\'"]push[\'"]/',
            $yaml
        );

        $if = $this->jobIf($yaml);
        $this->assertNotSame('', $if);

        $this->assertFalse(
            $this->deployIfAllows($if, 'workflow_run', 'failure', 'main', 'push'),
            'A failing Tests run on main must not deploy'
        );
        $this->assertFalse(
            $this->deployIfAllows($if, 'workflow_run', 'cancelled', 'main', 'push'),
            'A cancelled Tests run must not deploy'
        );
        $this->assertFalse(
            $this->deployIfAllows($if, 'workflow_run', 'success', 'fix/openplan3d-room-area', 'push'),
            'A green Tests run on a non-main branch must not deploy'
        );
        $this->assertFalse(
            $this->deployIfAllows($if, 'workflow_run', 'success', 'main', 'pull_request'),
            'A green Tests run triggered by a pull_request must not deploy'
        );
        $this->assertTrue(
            $this->deployIfAllows($if, 'workflow_run', 'success', 'main', 'push'),
            'Tests success on a push to main may deploy'
        );
        $this->assertFalse(
            $this->deployIfAllows($if, 'workflow_dispatch', null, null, null, 'skipped'),
            'Manual workflow_dispatch must not deploy without a green Tests run'
        );
        $this->assertTrue(
            $this->deployIfAllows($if, 'workflow_dispatch', null, null, null, 'success'),
            'Manual workflow_dispatch may deploy after Tests success'
        );
    }

    /**
     * @return list<string>
     */
    private function pathsFile(string $relative): array
    {
        $path = base_path($relative);
        $this->assertFileExists($path);
        $lines = preg_split('/\r\n|\n|\r/', (string) file_get_contents($path)) ?: [];
        $paths = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');
            if ($line !== '') {
                $paths[] = $line;
            }
        }

        return $paths;
    }

    private function workflow(string $name): string
    {
        $path = base_path('.github/workflows/'.$name);
        $this->assertFileExists($path);
        $yaml = file_get_contents($path);
        $this->assertNotFalse($yaml);

        return str_replace("\r\n", "\n", $yaml);
    }

    private function section(string $yaml, string $start, string $end): string
    {
        $from = strpos($yaml, $start);
        $this->assertNotFalse($from, "Missing {$start}");
        $to = strpos($yaml, $end, $from + strlen($start));
        $this->assertNotFalse($to, "Missing {$end}");

        return substr($yaml, $from, $to - $from);
    }

    private function jobIf(string $yaml): string
    {
        if (preg_match('/(?:^|\n)\s+deploy:\s*\n(?:\s+needs:\s*\[[^\]]+\]\s*\n)?\s+if:\s*\$\{\{\s*(.+?)\s*\}\}/s', $yaml, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    private function deployIfAllows(
        string $expression,
        string $eventName,
        ?string $conclusion,
        ?string $branch,
        ?string $runEvent,
        string $testsResult = 'skipped'
    ): bool {
        $php = $expression;
        $php = str_replace(
            [
                'needs.require-tests.result',
                'always()',
                'github.event.workflow_run.head_branch',
                'github.event.workflow_run.conclusion',
                'github.event.workflow_run.event',
                'github.event_name',
                '&&',
                '||',
            ],
            [
                var_export($testsResult, true),
                'true',
                var_export($branch, true),
                var_export($conclusion, true),
                var_export($runEvent, true),
                var_export($eventName, true),
                'and',
                'or',
            ],
            $php
        );

        $allowed = preg_match('/^[a-z0-9_\'"\s\(\)=\.\/\-]+$/i', $php) === 1;
        $this->assertTrue($allowed, 'Deploy if expression is not a simple boolean check: '.$expression.' => '.$php);

        return (bool) eval('return '.$php.';');
    }
}
