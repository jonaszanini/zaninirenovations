<?php

namespace Database\Factories;

use App\Models\ClientProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientProject>
 */
class ClientProjectFactory extends Factory
{
    protected $model = ClientProject::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Renovation',
            'client_name' => fake()->name(),
            'client_email' => fake()->safeEmail(),
            'client_phone' => fake()->phoneNumber(),
            'client_address' => fake()->streetAddress().', ON',
            'status' => ClientProject::STATUS_QUOTE_PENDING,
            'payment_plan' => 1,
            'billing_type' => ClientProject::BILLING_FIXED,
        ];
    }
}
