<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class StandardQuotationEditorJsTest extends TestCase
{
    public function test_b8_confirms_before_removing_a_job_or_material(): void
    {
        $js = $this->editorJs();

        $this->assertStringContainsString("confirmRemove('Remove ' + matName + '?'", $js);
        $this->assertStringContainsString("confirmRemove(jobMsg, 'Remove job'", $js);
        $this->assertStringContainsString('window.AdminDialog.confirm', $js);
        $this->assertStringContainsString("confirmLabel: 'Remove'", $js);
        $this->assertMatchesRegularExpression(
            '/if \(list\.querySelectorAll\(\'\.sq-material-row\'\)\.length <= 1\) return;[\s\S]*confirmRemove/',
            $js
        );
        $this->assertMatchesRegularExpression(
            '/if \(workCards\(\)\.length <= 1\) return;[\s\S]*confirmRemove\(jobMsg/',
            $js
        );
    }

    public function test_b9_restores_every_open_job_not_only_the_last(): void
    {
        $js = $this->editorJs();

        $this->assertStringContainsString('function restoreOpenJobs()', $js);
        $this->assertStringContainsString('function saveOpenJobs()', $js);
        $this->assertStringContainsString("form.addEventListener('submit', saveOpenJobs)", $js);
        $this->assertStringContainsString('restoreOpenJobs();', $js);
        $this->assertStringContainsString("'admin-sq-open-jobs:' + (form.getAttribute('data-quote-id') || 'new')", $js);

        $this->assertMatchesRegularExpression(
            '/workCards\(\)\.forEach\(function \(card, i\) \{[\s\S]*stored\.open\.indexOf\(i\)/',
            $js
        );
        $this->assertDoesNotMatchRegularExpression(
            '/restoreOpenJobs[\s\S]*open\[open\.length - 1\]/',
            $js
        );

        $indexes = $this->jobsToRestore(
            ['open' => [0, 2], 't' => 1_000],
            1_500,
            4,
            [1]
        );
        $this->assertSame([0, 1, 2], $indexes);
    }

    /**
     * @param  array{open: list<int>, t?: int}  $stored
     * @param  list<int>  $invalidIndexes
     * @return list<int>
     */
    private function jobsToRestore(array $stored, int $now, int $cardCount, array $invalidIndexes): array
    {
        $maxAge = 12 * 60 * 60 * 1000;
        if (isset($stored['t']) && ($now - (int) $stored['t']) > $maxAge) {
            return [];
        }
        $open = [];
        for ($i = 0; $i < $cardCount; $i++) {
            $shouldOpen = in_array($i, $stored['open'], true) || in_array($i, $invalidIndexes, true);
            if ($shouldOpen) {
                $open[] = $i;
            }
        }

        return $open;
    }

    private function editorJs(): string
    {
        $js = (string) file_get_contents(public_path('js/admin-standard-quotation.js'));
        $this->assertNotSame('', $js);

        return $js;
    }
}
