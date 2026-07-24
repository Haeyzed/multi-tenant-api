<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\PaymentGateway;
use App\Models\Central\PaymentGateway as PaymentGatewayModel;
use App\Payments\PaymentGatewayManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lists payment gateways for admin UI from the catalog, with driver fallback.
 */
final class PaymentGatewayCatalogService
{
    public function __construct(
        private readonly PaymentGatewayManager $gatewayManager,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(): array
    {
        if (Schema::hasTable('payment_gateways') && PaymentGatewayModel::query()->exists()) {
            return $this->fromCatalog();
        }

        return $this->fromDrivers();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        return collect($this->gatewayManager->available())
            ->map(function (string $name): array {
                $gateway = PaymentGateway::tryFrom($name);

                return [
                    'value' => $name,
                    'label' => $gateway?->label() ?? Str::headline(str_replace('_', ' ', $name)),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fromCatalog(): array
    {
        /** @var Collection<int, PaymentGatewayModel> $gateways */
        $gateways = PaymentGatewayModel::query()
            ->with(['currencies:id,code', 'countries:id,iso2'])
            ->orderBy('priority')
            ->get();

        return $gateways
            ->map(function (PaymentGatewayModel $gateway): array {
                return [
                    'id' => $gateway->id,
                    'name' => $gateway->name,
                    'slug' => $gateway->slug,
                    'driver' => $gateway->driver,
                    'priority' => $gateway->priority,
                    'is_active' => $gateway->is_active,
                    'is_fallback' => $gateway->is_fallback,
                    'supports_refunds' => $gateway->supports_refund,
                    'supports_recurring' => $gateway->supports_subscription,
                    'supports_webhook' => $gateway->supports_webhook,
                    'supports_partial_refund' => $gateway->supports_partial_refund,
                    'currencies' => $gateway->currencies->pluck('code')->unique()->values()->all(),
                    'countries' => $gateway->countries->pluck('iso2')->unique()->values()->all(),
                    'source' => 'catalog',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fromDrivers(): array
    {
        return collect($this->gatewayManager->available())
            ->map(function (string $name): array {
                $driver = $this->gatewayManager->driver($name);

                return [
                    'name' => $name,
                    'slug' => $name,
                    'driver' => $name,
                    'supports_refunds' => $driver->supportsRefunds(),
                    'supports_recurring' => $driver->supportsRecurring(),
                    'source' => 'driver',
                ];
            })
            ->values()
            ->all();
    }
}
