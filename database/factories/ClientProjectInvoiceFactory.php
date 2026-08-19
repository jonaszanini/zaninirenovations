<?php

namespace Database\Factories;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientProjectInvoice>
 */
class ClientProjectInvoiceFactory extends Factory
{
    protected $model = ClientProjectInvoice::class;

    public function definition(): array
    {
        $subtotal = 1000.00;
        $hst = round($subtotal * ClientProjectInvoice::HST_RATE, 2);

        return [
            'client_project_id' => ClientProject::factory(),
            'installment_number' => null,
            'installment_description' => 'Manual invoice',
            'invoice_number' => 'INV-'.fake()->unique()->numerify('####-####'),
            'items' => [
                ['description' => 'Labour', 'amount' => $subtotal, 'line_kind' => ClientProjectInvoice::LINE_KIND_LABOUR],
            ],
            'subtotal' => $subtotal,
            'hst' => $hst,
            'total' => round($subtotal + $hst, 2),
            'status' => ClientProjectInvoice::STATUS_DRAFT,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => ClientProjectInvoice::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function remindersPaused(): static
    {
        return $this->state(fn () => [
            'reminders_paused_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            $total = (float) ($attributes['total'] ?? 1130.00);

            return [
                'status' => ClientProjectInvoice::STATUS_PAID,
                'paid_at' => now(),
                'paid_amount' => $total,
                'payment_method' => ClientProjectInvoice::PAYMENT_METHOD_ETRANSFER,
            ];
        });
    }
}
