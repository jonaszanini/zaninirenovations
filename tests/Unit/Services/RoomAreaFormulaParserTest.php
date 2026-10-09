<?php

namespace Tests\Unit\Services;

use App\Services\RoomAreaFormulaParser;
use Tests\TestCase;

class RoomAreaFormulaParserTest extends TestCase
{
    public function test_shared_cases_match_javascript_parity_file(): void
    {
        $path = base_path('tests/js/fixtures/room-area-formula-cases.json');
        $this->assertFileExists($path);
        $cases = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $parser = new RoomAreaFormulaParser;
        $vars = RoomAreaFormulaParser::variablesFromMetrics($cases['variables']);

        foreach ($cases['ok'] as $row) {
            $result = $parser->evaluate($row['formula'], $vars);
            $this->assertTrue($result['ok'], $row['formula'].' '.$result['error']);
            $this->assertEqualsWithDelta($row['value'], $result['value'], 1e-9, $row['formula']);
        }

        foreach ($cases['overrides'] ?? [] as $row) {
            $local = RoomAreaFormulaParser::variablesFromMetrics(array_merge($cases['variables'], $row['variables'] ?? []));
            $result = $parser->evaluate($row['formula'], $local);
            $this->assertTrue($result['ok'], $row['formula'].' override '.$result['error']);
            $this->assertEqualsWithDelta($row['value'], $result['value'], 1e-9, $row['formula'].' override');
        }

        foreach ($cases['round'] as $row) {
            $this->assertEqualsWithDelta(
                $row['qty'],
                $parser->applyRounding($row['value'], $row['whole']),
                1e-9,
                'round '.$row['value']
            );
        }

        foreach ($cases['errors'] as $row) {
            $result = $parser->evaluate($row['formula'], $vars);
            $this->assertFalse($result['ok'], json_encode($row['formula']).' should fail');
            $this->assertStringContainsString($row['contains'], (string) $result['error']);
        }
    }

    public function test_parser_source_does_not_use_eval(): void
    {
        $php = file_get_contents(app_path('Services/RoomAreaFormulaParser.php'));
        $this->assertNotFalse($php);
        $this->assertDoesNotMatchRegularExpression('/\beval\s*\(/', $php);
        $js = file_get_contents(public_path('js/room-area-lib.js'));
        $this->assertDoesNotMatchRegularExpression('/\beval\s*\(/', $js);
        $this->assertDoesNotMatchRegularExpression('/new Function\s*\(/', $js);
        $this->assertStringContainsString('function evaluateFormula(', $js);
        $this->assertStringContainsString('function applyFormulaRounding(', $js);
    }
}
