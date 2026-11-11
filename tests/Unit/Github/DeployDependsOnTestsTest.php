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
            '/concurrency:\s*\n\s+group:\s+tests-\$\{\{\s*github\.workflow\s*\}\}-\$\{\{\s*github\.ref\s*\}\}-\$\{\{\s*inputs\.full_suite\s*&&\s*[\'"]full[\'"]\s*\|\|\s*[\'"]scoped[\'"]\s*\}\}\s*\n\s+cancel-in-progress:\s+\$\{\{\s*github\.ref\s*!=\s*[\'"]refs\/heads\/main[\'"]\s*\}\}/m',
            $yaml
        );
        $this->assertStringContainsString('dorny/paths-filter@v3', $yaml);
        $this->assertStringContainsString('tests/ci/classify-ci-changes.sh', $yaml);
        $this->assertStringContainsString('tests/ci/compute-ci-flags.sh', $yaml);
        $this->assertMatchesRegularExpression(
            '/^\s+changes:\s*$/m',
            $yaml
        );
        $changesBlock = $this->extractYamlJobBlock($yaml, 'changes');
        $this->assertStringContainsString('actions/setup-node@v4', $changesBlock, 'changes job must install Node before compute-ci-flags (picomatch)');
        $this->assertStringContainsString('npm ci', $changesBlock, 'changes job must run npm ci before match-workflow-filters');
        $this->assertMatchesRegularExpression(
            '/Setup Node[\s\S]*npm ci[\s\S]*id: flags/m',
            $changesBlock,
            'Node install must precede compute flags step'
        );
        $this->assertStringContainsString('tests/ci/run-js-browser-suite.sh', $yaml);
        $this->assertStringContainsString('tests/ci/run-js-unit-suite.sh', $yaml);
        $this->assertStringContainsString('global_test_deps:', $yaml);
        $this->assertStringContainsString('run_mysql_node', $yaml);
        $this->assertStringContainsString('RoomAreaEngineFeatureFlagTest.php', $yaml);
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
        $this->assertStringContainsString('workflow_call:', $yaml);
        $this->assertStringContainsString('run_unit_js', $yaml);
        $this->assertStringContainsString('run_php_unit', $yaml);
        $this->assertStringContainsString('run_js_unit', $yaml);
        $this->assertStringContainsString('cache: composer', $yaml);
        $this->assertStringContainsString('cache: npm', $yaml);
        $this->assertStringContainsString('actions/cache@v4', $yaml);
        $this->assertStringContainsString('PUPPETEER_CACHE_DIR', $yaml);
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
        $this->assertContains('tests/Unit/Services/BasementQuotationCalculatorTest.php', $unit);
        $this->assertNotContains('tests/Unit/Services/BasementQuotationCalculatorTest.php', $core);
        $this->assertContains('tests/Unit/Services/BasementQuotationProductivityOverlayTest.php', $core);
        $this->assertNotContains('tests/Unit/Services/BasementQuotationProductivityOverlayTest.php', $unit);
        $this->assertContains('tests/Unit/Services/PavingQuotationCalculatorTest.php', $core);
        $this->assertNotContains('tests/Unit/Services/PavingQuotationCalculatorTest.php', $unit);
        $this->assertContains('tests/Unit/Services/TrimmingQuotationCalculatorTest.php', $core);
        $this->assertNotContains('tests/Unit/Services/TrimmingQuotationCalculatorTest.php', $unit);
        $this->assertContains('tests/Unit/Services/LowDeckQuotationCalculatorTest.php', $unit);
        $this->assertContains('tests/Unit/Services/PavingMarbleCalculatorTest.php', $unit);
        $this->assertContains('tests/Unit/Services/ShedQuotationCalculatorTest.php', $unit);
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

    public function test_full_mysql_suite_unions_every_ci_database_list_including_main_only_extended(): void
    {
        $extended = $this->pathsFile('tests/ci/database-extended.txt');
        $this->assertContains(
            'tests/Feature/Admin/RoomAreaOpenPlan3DQuotationTest.php',
            $extended,
            'RoomAreaOpenPlan3DQuotationTest stays on the main/full extended list'
        );

        $roomAreaFilter = $this->pathsFile('tests/ci/database-filter-room-area.txt');
        $this->assertContains(
            'tests/Feature/Admin/RoomAreaOpenPlan3DQuotationTest.php',
            $roomAreaFilter,
            'PR #149 placed this test on the room-area PR filter; full suite still unions it via extended'
        );

        exec(
            'CI_MYSQL_SUITE=full bash '.escapeshellarg(base_path('tests/ci/select-database-paths.sh')),
            $full,
            $fullCode
        );
        $this->assertSame(0, $fullCode, 'select-database-paths.sh failed for full suite');
        $this->assertContains('tests/Feature/Admin/RoomAreaOpenPlan3DQuotationTest.php', $full);

        foreach (glob(base_path('tests/ci/database-filter-*.txt')) ?: [] as $listFile) {
            foreach ($this->pathsFile('tests/ci/'.basename($listFile)) as $path) {
                if (is_dir(base_path($path))) {
                    continue;
                }
                $this->assertContains(
                    $path,
                    $full,
                    basename($listFile).' path '.$path.' must run in CI_MYSQL_SUITE=full'
                );
            }
        }

        exec('bash '.escapeshellarg(base_path('tests/ci/report-full-ci-check.test.sh')).' 2>&1', $checkOut, $checkCode);
        $this->assertSame(0, $checkCode, implode("\n", $checkOut));
    }

    public function test_full_ci_workflow_pins_head_sha_and_reuses_tests_suite(): void
    {
        $yaml = $this->workflow('full-ci.yml');
        $this->assertStringContainsString('name: Full CI', $yaml);
        $this->assertMatchesRegularExpression('/pull_request:\s*\n\s+types:\s*\[labeled\]/', $yaml);
        $this->assertStringContainsString('workflow_dispatch:', $yaml);
        $this->assertStringContainsString('pr_number:', $yaml);
        $this->assertStringContainsString("github.event.label.name == 'full-ci'", $yaml);
        $this->assertStringContainsString('uses: ./.github/workflows/tests.yml', $yaml);
        $this->assertStringContainsString('full_suite: true', $yaml);
        $this->assertStringContainsString('checkout_ref:', $yaml);
        $this->assertStringContainsString('report-full-ci-check.sh', $yaml);
        $this->assertStringContainsString('CHECK_NAME:-full-ci', (string) file_get_contents(base_path('.github/scripts/report-full-ci-check.sh')));
        $this->assertMatchesRegularExpression(
            '/concurrency:\s*\n\s+group:\s+full-ci-pr-/m',
            $yaml
        );
        $this->assertStringContainsString('cancel-in-progress: true', $yaml);
        $this->assertStringContainsString('checks: write', $yaml);

        $tests = $this->workflow('tests.yml');
        $this->assertStringContainsString('inputs.full_suite', $tests);
        $this->assertStringContainsString("CI_MYSQL_SUITE:", $tests);
        $this->assertStringContainsString("inputs.full_suite == true", $tests);
        $this->assertStringContainsString('needs.changes.outputs.run_unit_js', $tests);
        $this->assertStringContainsString("docs_only != 'true'", $tests);
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

    public function test_quotation_help_and_workflow_run_on_pr_when_quotation_views_change(): void
    {
        $quotationFilter = $this->pathsFile('tests/ci/database-filter-quotation.txt');
        $this->assertContains('tests/Feature/Admin/QuotationHelpTipBrowserTest.php', $quotationFilter);
        $this->assertContains('tests/Feature/Admin/StairsQuotationPagesTest.php', $quotationFilter);
        $this->assertContains('tests/Feature/Admin/BasementDemoChoiceBrowserTest.php', $quotationFilter);
        $this->assertContains(
            'tests/Feature/Admin/PavingMarbleBrowserTest.php',
            $quotationFilter,
            'Paving view / info-tip wrapper PRs must run PavingMarbleBrowserTest, not only main extended.txt'
        );
        $this->assertContains(
            'tests/Feature/Admin/QuotationEditorFooterBrowserTest.php',
            $quotationFilter,
            'Footer / header partial PRs must run QuotationEditorFooterBrowserTest on the quotation filter'
        );
        $this->assertContains(
            'tests/Feature/Admin/QuotationEditorFooterTest.php',
            $quotationFilter,
            'Footer / header partial PRs must run QuotationEditorFooterTest on the quotation filter'
        );
        $this->assertContains(
            'tests/Feature/Admin/QuotationEditorControlInventoryTest.php',
            $quotationFilter,
            'Publish / Save as draft label PRs must run QuotationEditorControlInventoryTest on the quotation filter'
        );
        exec(
            'CI_MYSQL_SUITE=pr CI_MYSQL_FILTER_QUOTATION=true bash '
            .escapeshellarg(base_path('tests/ci/select-database-paths.sh')),
            $selected,
            $selectCode
        );
        $this->assertSame(0, $selectCode, 'select-database-paths.sh failed for quotation filter');
        $this->assertContains(
            'tests/Feature/Admin/PavingMarbleBrowserTest.php',
            $selected,
            'quotation-filter PRs must actually select PavingMarbleBrowserTest in the mysql path list'
        );
        $this->assertContains(
            'tests/Feature/Admin/QuotationEditorFooterBrowserTest.php',
            $selected,
            'quotation-filter PRs must actually select QuotationEditorFooterBrowserTest'
        );
        $this->assertContains(
            'tests/Feature/Admin/QuotationEditorControlInventoryTest.php',
            $selected,
            'quotation-filter PRs must actually select QuotationEditorControlInventoryTest'
        );
        $core = $this->pathsFile('tests/ci/database-core.txt');
        $this->assertContains('tests/Feature/Admin/StairsQuotationPagesTest.php', $core);
        $this->assertNotContains(
            'tests/Feature/Admin/QuotationHelpTipBrowserTest.php',
            $core,
            'Help-tip puppeteer belongs on the quotation filter (mysql node) so unrelated PRs do not run it without Chrome'
        );
        $sharedFilter = $this->pathsFile('tests/ci/database-filter-shared-prices.txt');
        $this->assertContains('tests/Unit/Services/BasementQuotationProductivityOverlayTest.php', $sharedFilter);
        $this->assertContains('tests/Unit/Services/TrimmingQuotationCalculatorTest.php', $sharedFilter);
        $this->assertContains('tests/Unit/Services/SharedWorkCatalogMaterialPriceTest.php', $sharedFilter);
        $this->assertNotContains('tests/Unit/Services/BasementQuotationCalculatorTest.php', $sharedFilter);
    }

    public function test_matcher_fail_closed_script_passes(): void
    {
        exec('bash '.escapeshellarg(base_path('tests/ci/matcher-fail-closed.test.sh')).' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
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

    public function test_browser_manifest_lists_every_browser_spec(): void
    {
        exec('node '.escapeshellarg(base_path('tests/ci/verify-browser-manifest.js')).' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
    }

    public function test_picomatch_workflow_filter_spot_checks(): void
    {
        exec('node '.escapeshellarg(base_path('tests/ci/picomatch-tracked-files-audit.js')).' 2>&1', $output, $code);
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
        $this->assertFalse(
            $this->deployIfAllows($if, 'workflow_run', 'success', 'main', 'push', 'skipped'),
            'workflow_run must not deploy while require-tests is skipped (Nov 6 SHA race)'
        );
        $this->assertTrue(
            $this->deployIfAllows($if, 'workflow_run', 'success', 'main', 'push', 'success'),
            'Tests success on a push to main may deploy only after require-tests pins that SHA'
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

    public function test_deploy_pins_exact_green_tested_sha_and_does_not_fall_back_to_tip(): void
    {
        $yaml = $this->workflow('deploy.yml');
        $require = $this->extractYamlJobBlock($yaml, 'require-tests');
        $deploy = $this->extractYamlJobBlock($yaml, 'deploy');

        $this->assertStringNotContainsString(
            "if: github.event_name == 'workflow_dispatch'",
            $require,
            'require-tests must also run for workflow_run so the tested SHA is pinned'
        );
        $this->assertStringContainsString('pin-deploy-sha.sh', $require);
        $this->assertStringContainsString('require-tests-conclusion.sh', $require);
        $this->assertStringContainsString('WORKFLOW_RUN_SHA', $require);
        $this->assertStringContainsString('DISPATCH_SHA', $require);
        $this->assertStringContainsString('repos/${REPO}/commits/main', $require);
        $this->assertStringContainsString('PINNED_SHA', $require);
        $this->assertStringContainsString('needs.require-tests.outputs.sha', $deploy);
        $this->assertDoesNotMatchRegularExpression(
            '/head_sha\s*\|\|\s*github\.sha/',
            $yaml,
            'Checkout must not fall back from the Tests SHA to the current default-branch tip'
        );
        $this->assertStringContainsString('git rev-parse HEAD', $deploy);
        $this->assertMatchesRegularExpression(
            '/Recheck pin is still current main before deploy side effects[\s\S]*Deploy to server/',
            $deploy,
            'Fresh main HEAD must be rechecked after environment waits and immediately before rsync'
        );
        $this->assertStringContainsString('pin-deploy-sha.sh', $deploy);
        $deployIf = $this->jobIf($yaml);
        $this->assertStringContainsString("needs.require-tests.result == 'success'", $deployIf);

        exec('bash '.escapeshellarg(base_path('tests/ci/pin-deploy-sha.test.sh')).' 2>&1', $pinOutput, $pinCode);
        $this->assertSame(0, $pinCode, implode("\n", $pinOutput));

        exec('bash '.escapeshellarg(base_path('tests/ci/require-tests-conclusion.test.sh')).' 2>&1', $testsOutput, $testsCode);
        $this->assertSame(0, $testsCode, implode("\n", $testsOutput));
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

    private function extractYamlJobBlock(string $yaml, string $job): string
    {
        $pattern = '/^\s{2}'.preg_quote($job, '/').':\s*\n/m';
        if (! preg_match($pattern, $yaml, $startMatch, PREG_OFFSET_CAPTURE)) {
            $this->fail("Missing job {$job}");
        }
        $start = $startMatch[0][1];
        if (preg_match('/^\s{2}[a-z0-9_]+:\s*\n/m', $yaml, $nextMatch, PREG_OFFSET_CAPTURE, $start + strlen($startMatch[0][0]))) {
            return substr($yaml, $start, $nextMatch[0][1] - $start);
        }

        return substr($yaml, $start);
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
