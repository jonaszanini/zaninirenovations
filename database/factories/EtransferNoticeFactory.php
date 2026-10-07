<?php

namespace Database\Factories;

use App\Models\EtransferNotice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EtransferNotice>
 */
class EtransferNoticeFactory extends Factory
{
    protected $model = EtransferNotice::class;

    public function definition(): array
    {
        return [
            'received_at' => now()->subHours(fake()->numberBetween(1, 48)),
            'sender_name' => fake()->name(),
            'sender_email' => fake()->unique()->safeEmail(),
            'amount' => 1130.00,
            'currency' => EtransferNotice::CURRENCY_CAD,
            'reference_number' => 'CA'.fake()->unique()->bothify('??##??##??##'),
            'message' => 'INV-2026-0001',
            'raw_text' => null,
            'source' => EtransferNotice::SOURCE_API,
            'status' => EtransferNotice::STATUS_PENDING,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => EtransferNotice::STATUS_PENDING]);
    }

    public function matched(): static
    {
        return $this->state(fn () => ['status' => EtransferNotice::STATUS_MATCHED]);
    }

    public function ignored(): static
    {
        return $this->state(fn () => ['status' => EtransferNotice::STATUS_IGNORED]);
    }

    public function manual(): static
    {
        return $this->state(fn () => ['source' => EtransferNotice::SOURCE_MANUAL]);
    }
}
