<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\InvoiceStatus;
use App\Models\Central\Invoice;
use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $subtotal = 49.99;

        return [
            'tenant_id' => Tenant::factory(),
            'number' => 'INV-'.Str::upper(Str::random(8)),
            'status' => InvoiceStatus::OPEN,
            'subtotal' => $subtotal,
            'tax_rate' => 0,
            'tax' => 0,
            'total' => $subtotal,
            'amount_paid' => 0,
            'currency' => 'NGN',
            'issued_at' => now(),
            'due_at' => now()->addDays(7),
        ];
    }
}
