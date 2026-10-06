<?php

namespace Database\Factories;

use App\Models\ClientProject;
use App\Models\ClientProjectExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientProjectExpense>
 */
class ClientProjectExpenseFactory extends Factory
{
    protected $model = ClientProjectExpense::class;

    public function definition(): array
    {
        return [
            'client_project_id' => ClientProject::factory(),
            'occurred_on' => fake()->dateTimeBetween('-30 days', 'now'),
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'expense_ref' => fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
