<?php

namespace App\Support;

/**
 * Common fence gate sizes used by the calculator and the editor.
 *
 * Assumption (confirm with install method): a single leaf needs one hinge
 * post and one latch post; a double drive gate needs two hinge posts and
 * no extra latch. Hardware is one set per leaf.
 */
final class FenceGateSpec
{
    public const TYPE_SINGLE = 'single';

    public const TYPE_DOUBLE = 'double';

    public const DEFAULT_KEY = '4_single';

    /**
     * @var array<string, array{width_ft: float, type: string, label: string}>
     */
    public const PRESETS = [
        '3_single' => ['width_ft' => 3.0, 'type' => self::TYPE_SINGLE, 'label' => '3 ft single'],
        '4_single' => ['width_ft' => 4.0, 'type' => self::TYPE_SINGLE, 'label' => '4 ft single'],
        '5_single' => ['width_ft' => 5.0, 'type' => self::TYPE_SINGLE, 'label' => '5 ft single'],
        '8_double' => ['width_ft' => 8.0, 'type' => self::TYPE_DOUBLE, 'label' => '8 ft double'],
        '10_double' => ['width_ft' => 10.0, 'type' => self::TYPE_DOUBLE, 'label' => '10 ft double'],
        '12_double' => ['width_ft' => 12.0, 'type' => self::TYPE_DOUBLE, 'label' => '12 ft double'],
    ];

    public function __construct(
        public string $key,
        public float $widthFt,
        public string $type,
        public string $label,
    ) {}

    public static function default(): self
    {
        return self::fromKey(self::DEFAULT_KEY);
    }

    public static function fromKey(?string $key): self
    {
        $key = is_string($key) ? trim($key) : '';
        if ($key === '' || ! isset(self::PRESETS[$key])) {
            $key = self::DEFAULT_KEY;
        }

        $preset = self::PRESETS[$key];

        return new self($key, (float) $preset['width_ft'], (string) $preset['type'], (string) $preset['label']);
    }

    /**
     * @param  array<string, mixed>  $side
     */
    public static function fromSide(array $side): self
    {
        if (! empty($side['gate_preset'])) {
            return self::fromKey((string) $side['gate_preset']);
        }

        $width = isset($side['gate_width_ft']) && is_numeric($side['gate_width_ft'])
            ? (float) $side['gate_width_ft']
            : null;
        $type = isset($side['gate_type']) ? strtolower(trim((string) $side['gate_type'])) : null;

        foreach (self::PRESETS as $key => $preset) {
            if ($width !== null && abs($preset['width_ft'] - $width) < 0.01) {
                if ($type === null || $type === $preset['type']) {
                    return self::fromKey($key);
                }
            }
        }

        return self::default();
    }

    /**
     * @return list<array{key: string, label: string, width_ft: float, type: string}>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::PRESETS as $key => $preset) {
            $out[] = [
                'key' => $key,
                'label' => $preset['label'],
                'width_ft' => $preset['width_ft'],
                'type' => $preset['type'],
            ];
        }

        return $out;
    }

    public function isDouble(): bool
    {
        return $this->type === self::TYPE_DOUBLE;
    }

    public function leafCount(): int
    {
        return $this->isDouble() ? 2 : 1;
    }

    public function hardwareSets(): int
    {
        return $this->leafCount();
    }

    public function hingePostCount(): int
    {
        return $this->isDouble() ? 2 : 1;
    }

    /** Posts needed for a gate that is not on a run (no existing posts). */
    public function standalonePostCount(): int
    {
        return $this->isDouble() ? 2 : 2;
    }

    public function shortLabel(): string
    {
        return $this->label;
    }
}
