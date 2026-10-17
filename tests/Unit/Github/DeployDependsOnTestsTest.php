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
            '/concurrency:\s*\n\s+group:\s+tests-\$\{\{\s*github\.ref\s*\}\}\s*\n\s+cancel-in-progress:\s+true/m',
            $yaml
        );
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
    }

    public function test_tests_workflow_invokes_each_listed_phpunit_path_separately(): void
    {
        $yaml = $this->workflow('tests.yml');

        $this->assertSame(
            1,
            substr_count($yaml, 'tests/Unit/Github/DeployDependsOnTestsTest.php'),
            'DeployDependsOnTestsTest must appear once (no duplicate artisan entry)'
        );
        $this->assertStringContainsString('for f in "${files[@]}"', $yaml);
        $this->assertStringContainsString('php artisan test "$f" || failed+=("$f")', $yaml);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($yaml, 'failed+=("$f")'),
            'Unit and Database loops must collect failures instead of stopping at the first'
        );
        $this->assertStringContainsString('${#failed[@]}', $yaml);
        $this->assertDoesNotMatchRegularExpression(
            '/php artisan test \\\\\s*\n\s+tests\//',
            $yaml,
            'PHPUnit 9 only runs the first path; do not pass multiple files to one artisan test'
        );

        $unit = $this->listedPaths($yaml, 'Unit tests (no database)', 'Database tests (MySQL)');
        $db = $this->listedPaths($yaml, 'Database tests (MySQL)', 'Upload Shared prices geometry');

        $this->assertContains('tests/Unit/Github/DeployDependsOnTestsTest.php', $unit);
        $this->assertContains('tests/Unit/Services/OpenPlan3DPlanValidatorTest.php', $unit);
        $this->assertContains('tests/Feature/Admin/RoomAreaOpenPlan3DSaveTest.php', $db);
        $this->assertContains('tests/Unit/Services/RoomAreaDrawingImporterTest.php', $db);
        $this->assertNotContains('tests/Unit/Services/RoomAreaDrawingImporterTest.php', $unit);
        $this->assertGreaterThan(20, count($unit));
        $this->assertGreaterThan(80, count($db));
        $this->assertSame(count($unit), count(array_unique($unit)));
        $this->assertSame(count($db), count(array_unique($db)));

        foreach (array_merge($unit, $db) as $path) {
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
    private function listedPaths(string $yaml, string $startHeading, string $endHeading): array
    {
        $from = strpos($yaml, '- name: '.$startHeading);
        $this->assertNotFalse($from, 'Missing '.$startHeading);
        $to = strpos($yaml, '- name: '.$endHeading, $from + 10);
        $this->assertNotFalse($to, 'Missing '.$endHeading);
        $block = substr($yaml, $from, $to - $from);
        preg_match_all('/^\s+(tests\/\S+)/m', $block, $m);

        return $m[1];
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
