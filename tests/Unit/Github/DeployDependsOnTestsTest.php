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
        $this->assertStringContainsString('run_database', $yaml);
        $this->assertStringContainsString("push:\n    branches:\n      - main", $yaml);
        $this->assertStringContainsString('pull_request:', $yaml);
        $this->assertMatchesRegularExpression(
            '/pull_request:\s*\n\s+types:\s*\[opened,\s*synchronize,\s*reopened,\s*ready_for_review\]/',
            $yaml
        );
        $this->assertStringContainsString('workflow_dispatch:', $yaml);
        $this->assertStringContainsString('openplan3d-editor-bridge.test.js', $yaml);
        $this->assertStringContainsString('openplan3d-editor-browser.test.js', $yaml);
        $this->assertMatchesRegularExpression(
            '/fetch-depth:\s*0/',
            $yaml,
            'Shallow clone hides 86e68a3e from QuotationEditorControlInventoryTest'
        );
        $this->assertStringContainsString('tests/ci/run-database-suite.sh', $yaml);
        $runScript = file_get_contents(base_path('tests/ci/run-database-suite.sh'));
        $this->assertNotFalse($runScript);
        $this->assertStringContainsString('php artisan test --parallel', $runScript);
    }

    public function test_tests_workflow_lists_every_ci_database_and_unit_path_on_disk(): void
    {
        $yaml = $this->workflow('tests.yml');
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
        $this->assertContains('tests/Feature/Admin/RoomAreaOpenPlan3DSaveTest.php', $unit);
        $this->assertContains('tests/Unit/Services/RoomAreaDrawingImporterTest.php', $unit);
        $this->assertContains('tests/Feature/Admin/RoomAreaCanvasObjectsTest.php', $extended);
        $this->assertContains('tests/Feature/SharedWorksNormalizedLabelMigrationTest.php', $core);
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
