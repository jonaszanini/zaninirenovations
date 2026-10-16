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
        $this->assertStringContainsString('workflow_dispatch:', $yaml);
        $this->assertStringContainsString('openplan3d-editor-bridge.test.js', $yaml);
        $this->assertStringContainsString('openplan3d-editor-browser.test.js', $yaml);
        $this->assertStringContainsString('php artisan test tests/Unit/Github/DeployDependsOnTestsTest.php', $yaml);
        $this->assertStringContainsString('php artisan test tests/Feature/Admin/RoomAreaOpenPlan3DSaveTest.php', $yaml);
    }

    public function test_deploy_requires_tests_success_and_is_skipped_when_tests_fail(): void
    {
        $yaml = $this->workflow('deploy.yml');
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

        $if = $this->jobIf($yaml);
        $this->assertNotSame('', $if);

        $this->assertFalse(
            $this->deployIfAllows($if, 'workflow_run', 'failure', 'main'),
            'A failing Tests run on main must not deploy'
        );
        $this->assertFalse(
            $this->deployIfAllows($if, 'workflow_run', 'cancelled', 'main'),
            'A cancelled Tests run must not deploy'
        );
        $this->assertFalse(
            $this->deployIfAllows($if, 'workflow_run', 'success', 'fix/openplan3d-room-area'),
            'A green Tests run on a non-main branch must not deploy'
        );
        $this->assertTrue(
            $this->deployIfAllows($if, 'workflow_run', 'success', 'main'),
            'Tests success on main may deploy'
        );
        $this->assertTrue(
            $this->deployIfAllows($if, 'workflow_dispatch', null, null),
            'Manual workflow_dispatch remains allowed'
        );
    }

    private function workflow(string $name): string
    {
        $path = base_path('.github/workflows/'.$name);
        $this->assertFileExists($path);
        $yaml = file_get_contents($path);
        $this->assertNotFalse($yaml);

        return $yaml;
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
        if (preg_match('/jobs:\s*\n\s+deploy:\s*\n\s+if:\s*\$\{\{\s*(.+?)\s*\}\}/s', $yaml, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    private function deployIfAllows(string $expression, string $eventName, ?string $conclusion, ?string $branch): bool
    {
        $php = $expression;
        $php = str_replace(
            [
                'github.event_name',
                'github.event.workflow_run.conclusion',
                'github.event.workflow_run.head_branch',
                '&&',
                '||',
            ],
            [
                var_export($eventName, true),
                var_export($conclusion, true),
                var_export($branch, true),
                'and',
                'or',
            ],
            $php
        );

        $allowed = preg_match('/^[a-z0-9_\'"\s\(\)=\.\/\-]+$/i', $php) === 1;
        $this->assertTrue($allowed, 'Deploy if expression is not a simple boolean check: '.$expression);

        return (bool) eval('return '.$php.';');
    }
}
