<?php

namespace App\Services;

/**
 * Safe arithmetic parser for Room Area assembly formulas.
 *
 * Safe recursive-descent parser. Keep in sync with evaluateFormula() in public/js/room-area-lib.js.
 */
class RoomAreaFormulaParser
{
    public const MAX_LENGTH = 200;

    public const MAX_DEPTH = 24;

    /**
     * @return list<string>
     */
    public static function allowedNames(): array
    {
        return [
            'floor_sf', 'floor', 'area_sqft', 'inside_area_sqft',
            'net_wall_sf', 'net_wall', 'net_wall_area_sqft', 'wall_sf', 'wall_area_sqft',
            'ceiling_sf', 'ceiling', 'ceiling_area_sqft',
            'perimeter_lf', 'perimeter',
            'baseboard_lf', 'baseboard',
            'casing_lf', 'casing',
            'door_count', 'doors',
            'window_count', 'windows',
            'opening_count', 'openings',
            'wall_height_ft', 'wall_height', 'height',
            'flooring_boxes', 'boxes',
            'flooring_waste_percent', 'waste',
        ];
    }

    /**
     * @param  array<string, float|int>  $metrics
     * @return array<string, float>
     */
    public static function variablesFromMetrics(array $metrics): array
    {
        $floor = (float) ($metrics['area_sqft'] ?? $metrics['inside_area_sqft'] ?? $metrics['floor_sf'] ?? 0);
        $inside = (float) ($metrics['inside_area_sqft'] ?? $floor);
        $netWall = (float) ($metrics['net_wall_area_sqft'] ?? $metrics['wall_area_sqft'] ?? $metrics['net_wall_sf'] ?? 0);
        $ceiling = (float) ($metrics['ceiling_area_sqft'] ?? $metrics['ceiling_sf'] ?? $floor);
        $perimeter = (float) ($metrics['perimeter_lf'] ?? $metrics['perimeter'] ?? 0);
        $baseboard = (float) ($metrics['baseboard_lf'] ?? $metrics['baseboard'] ?? $perimeter);
        $casing = (float) ($metrics['casing_lf'] ?? $metrics['casing'] ?? 0);
        $doors = (float) ($metrics['door_count'] ?? $metrics['doors'] ?? 0);
        $windows = (float) ($metrics['window_count'] ?? $metrics['windows'] ?? 0);
        $openings = (float) ($metrics['opening_count'] ?? $metrics['openings'] ?? 0);
        $height = (float) ($metrics['wall_height_ft'] ?? $metrics['wall_height'] ?? $metrics['height'] ?? 8);
        $boxes = (float) ($metrics['flooring_boxes'] ?? $metrics['boxes'] ?? 0);
        $waste = (float) ($metrics['flooring_waste_percent'] ?? $metrics['waste'] ?? 10);

        return [
            'floor_sf' => $floor,
            'floor' => $floor,
            'area_sqft' => $floor,
            'inside_area_sqft' => $inside,
            'net_wall_sf' => $netWall,
            'net_wall' => $netWall,
            'net_wall_area_sqft' => $netWall,
            'wall_sf' => $netWall,
            'wall_area_sqft' => $netWall,
            'ceiling_sf' => $ceiling,
            'ceiling' => $ceiling,
            'ceiling_area_sqft' => $ceiling,
            'perimeter_lf' => $perimeter,
            'perimeter' => $perimeter,
            'baseboard_lf' => $baseboard,
            'baseboard' => $baseboard,
            'casing_lf' => $casing,
            'casing' => $casing,
            'door_count' => $doors,
            'doors' => $doors,
            'window_count' => $windows,
            'windows' => $windows,
            'opening_count' => $openings,
            'openings' => $openings,
            'wall_height_ft' => $height,
            'wall_height' => $height,
            'height' => $height,
            'flooring_boxes' => $boxes,
            'boxes' => $boxes,
            'flooring_waste_percent' => $waste,
            'waste' => $waste,
        ];
    }

    /**
     * @param  array<string, float|int>  $variables
     * @return array{ok: bool, value: ?float, error: ?string}
     */
    public function evaluate(string $formula, array $variables = []): array
    {
        $raw = trim($formula);
        if ($raw === '') {
            return $this->fail('Enter a formula.');
        }
        if (strlen($raw) > self::MAX_LENGTH) {
            return $this->fail('Formula is too long.');
        }

        try {
            $tokens = $this->tokenize($raw);
            $parser = new FormulaTokenReader($tokens);
            $value = $this->parseExpr($parser, $variables, 0);
            if (! $parser->done()) {
                return $this->fail('Unexpected text after the formula.');
            }
            if (! is_finite($value)) {
                return $this->fail('The formula did not produce a number.');
            }

            return ['ok' => true, 'value' => $value, 'error' => null];
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function applyRounding(float $value, bool $wholeUnits): float
    {
        if (! is_finite($value)) {
            return 0.0;
        }
        if ($wholeUnits) {
            return max(0.0, (float) ceil($value - 1e-12));
        }

        return max(0.0, $this->roundTo($value, 2));
    }

    /**
     * @return array{ok: bool, value: ?float, error: ?string}
     */
    private function fail(string $error): array
    {
        return ['ok' => false, 'value' => null, 'error' => $error];
    }

    /**
     * @return list<array{type: string, value: string}>
     */
    private function tokenize(string $raw): array
    {
        $tokens = [];
        $len = strlen($raw);
        $i = 0;
        while ($i < $len) {
            $ch = $raw[$i];
            if (ctype_space($ch)) {
                $i++;
                continue;
            }
            if (str_contains('+-*/(),', $ch)) {
                $tokens[] = ['type' => $ch, 'value' => $ch];
                $i++;
                continue;
            }
            if (ctype_digit($ch) || ($ch === '.' && $i + 1 < $len && ctype_digit($raw[$i + 1]))) {
                $start = $i;
                if ($ch === '.') {
                    $i++;
                }
                while ($i < $len && ctype_digit($raw[$i])) {
                    $i++;
                }
                if ($i < $len && $raw[$i] === '.') {
                    $i++;
                    if ($i >= $len || ! ctype_digit($raw[$i])) {
                        throw new \InvalidArgumentException('Enter a number after the decimal point.');
                    }
                    while ($i < $len && ctype_digit($raw[$i])) {
                        $i++;
                    }
                }
                if ($i < $len && ($raw[$i] === 'e' || $raw[$i] === 'E')) {
                    throw new \InvalidArgumentException('Scientific notation is not allowed. Write the full number.');
                }
                $tokens[] = ['type' => 'number', 'value' => substr($raw, $start, $i - $start)];
                continue;
            }
            if (ctype_alpha($ch) || $ch === '_') {
                $start = $i;
                $i++;
                while ($i < $len && (ctype_alnum($raw[$i]) || $raw[$i] === '_')) {
                    $i++;
                }
                $tokens[] = ['type' => 'name', 'value' => strtolower(substr($raw, $start, $i - $start))];
                continue;
            }

            throw new \InvalidArgumentException('Unexpected “'.$ch.'”. Use + − × ÷, brackets, and names like floor_sf.');
        }

        return $tokens;
    }

    /**
     * @param  array<string, float|int>  $variables
     */
    private function parseExpr(FormulaTokenReader $parser, array $variables, int $depth): float
    {
        $value = $this->parseTerm($parser, $variables, $depth);
        while ($parser->is('+') || $parser->is('-')) {
            $op = $parser->take()['type'];
            $right = $this->parseTerm($parser, $variables, $depth);
            $value = $op === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    /**
     * @param  array<string, float|int>  $variables
     */
    private function parseTerm(FormulaTokenReader $parser, array $variables, int $depth): float
    {
        $value = $this->parseUnary($parser, $variables, $depth);
        while ($parser->is('*') || $parser->is('/')) {
            $op = $parser->take()['type'];
            $right = $this->parseUnary($parser, $variables, $depth);
            if ($op === '/') {
                if (abs($right) < 1e-15) {
                    throw new \InvalidArgumentException('Division by zero.');
                }
                $value = $value / $right;
            } else {
                $value = $value * $right;
            }
        }

        return $value;
    }

    /**
     * @param  array<string, float|int>  $variables
     */
    private function parseUnary(FormulaTokenReader $parser, array $variables, int $depth): float
    {
        if ($parser->is('+')) {
            $parser->take();

            return $this->parseUnary($parser, $variables, $depth);
        }
        if ($parser->is('-')) {
            $parser->take();

            return -1 * $this->parseUnary($parser, $variables, $depth);
        }

        return $this->parsePrimary($parser, $variables, $depth);
    }

    /**
     * @param  array<string, float|int>  $variables
     */
    private function parsePrimary(FormulaTokenReader $parser, array $variables, int $depth): float
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('Formula is too nested.');
        }
        if ($parser->is('number')) {
            return (float) $parser->take()['value'];
        }
        if ($parser->is('(')) {
            $parser->take();
            $value = $this->parseExpr($parser, $variables, $depth + 1);
            if (! $parser->is(')')) {
                throw new \InvalidArgumentException('Missing ).');
            }
            $parser->take();

            return $value;
        }
        if ($parser->is('name')) {
            $name = $parser->take()['value'];
            if ($parser->is('(')) {
                return $this->parseCall($name, $parser, $variables, $depth);
            }
            if (! array_key_exists($name, $variables)) {
                throw new \InvalidArgumentException(
                    'Unknown name: '.$name.'. Use floor_sf, net_wall_sf, ceiling_sf, perimeter_lf, casing_lf, door_count, window_count, wall_height, or waste.'
                );
            }

            return (float) $variables[$name];
        }
        if ($parser->done()) {
            throw new \InvalidArgumentException('The formula ended early. Check for a missing number.');
        }

        throw new \InvalidArgumentException('Use + − × ÷, brackets, and names like floor_sf.');
    }

    /**
     * @param  array<string, float|int>  $variables
     */
    private function parseCall(string $name, FormulaTokenReader $parser, array $variables, int $depth): float
    {
        $parser->take(); // (
        $args = [];
        if (! $parser->is(')')) {
            $args[] = $this->parseExpr($parser, $variables, $depth + 1);
            while ($parser->is(',')) {
                $parser->take();
                $args[] = $this->parseExpr($parser, $variables, $depth + 1);
            }
        }
        if (! $parser->is(')')) {
            throw new \InvalidArgumentException('Missing ).');
        }
        $parser->take();

        return $this->callFunction($name, $args);
    }

    /**
     * @param  list<float>  $args
     */
    private function callFunction(string $name, array $args): float
    {
        $count = count($args);
        return match ($name) {
            'ceil' => $this->oneArg($name, $args, fn (float $n) => (float) ceil($n)),
            'floor' => $this->oneArg($name, $args, fn (float $n) => (float) floor($n)),
            'abs' => $this->oneArg($name, $args, fn (float $n) => abs($n)),
            'round' => $this->callRound($args),
            'min' => $this->atLeastTwo($name, $args, fn (array $nums) => min($nums)),
            'max' => $this->atLeastTwo($name, $args, fn (array $nums) => max($nums)),
            default => throw new \InvalidArgumentException(
                'Unknown function: '.$name.'. Use ceil, floor, round, min, max, or abs.'
            ),
        };
    }

    /**
     * @param  list<float>  $args
     * @param  callable(float): float  $fn
     */
    private function oneArg(string $name, array $args, callable $fn): float
    {
        if (count($args) !== 1) {
            throw new \InvalidArgumentException($name.' needs one number inside the brackets.');
        }

        return $fn($args[0]);
    }

    /**
     * @param  list<float>  $args
     * @param  callable(list<float>): float  $fn
     */
    private function atLeastTwo(string $name, array $args, callable $fn): float
    {
        if (count($args) < 2) {
            throw new \InvalidArgumentException($name.' needs at least two numbers.');
        }

        return (float) $fn($args);
    }

    /**
     * @param  list<float>  $args
     */
    private function callRound(array $args): float
    {
        $count = count($args);
        if ($count < 1 || $count > 2) {
            throw new \InvalidArgumentException('round needs one number, or a number and the digits to keep.');
        }
        $digits = $count === 2 ? (int) round($args[1]) : 0;
        if ($digits < 0 || $digits > 9) {
            throw new \InvalidArgumentException('round digits must be between 0 and 9.');
        }

        return $this->roundTo($args[0], $digits);
    }

    public function roundTo(float $value, int $digits): float
    {
        $factor = 10 ** $digits;
        $shifted = $value * $factor;
        $sign = $shifted < 0 ? -1.0 : 1.0;

        return $sign * round(abs($shifted) + 1e-9, 0) / $factor;
    }
}

/**
 * @internal
 */
class FormulaTokenReader
{
    /** @var list<array{type: string, value: string}> */
    private array $tokens;

    private int $i = 0;

    /**
     * @param  list<array{type: string, value: string}>  $tokens
     */
    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
    }

    public function done(): bool
    {
        return $this->i >= count($this->tokens);
    }

    public function is(string $type): bool
    {
        return ! $this->done() && $this->tokens[$this->i]['type'] === $type;
    }

    /**
     * @return array{type: string, value: string}
     */
    public function take(): array
    {
        if ($this->done()) {
            throw new \InvalidArgumentException('The formula ended early. Check for a missing number.');
        }

        return $this->tokens[$this->i++];
    }
}
