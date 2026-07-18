<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Billing;

use App\Http\Controllers\Controller;
use App\Services\Central\Billing\PaymentWebhookService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

/**
 * Public webhook endpoints for live payment provider callbacks.
 */
#[Group('Central Billing Webhooks', description: 'Inbound payment provider webhooks.', weight: 141)]
final class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentWebhookService $webhooks,
    ) {}

    /**
     * Accept and process a payment provider webhook.
     *
     * @param  Request  $request
     * @param  string  $gateway
     * @return JsonResponse
     *
     * @throws AccessDeniedHttpException|Throwable
     */
    #[Endpoint(operationId: 'billing.paymentWebhook', title: 'Payment webhook', description: 'Verify and apply Stripe, Paystack, or Flutterwave webhook events.')]
    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        $result = $this->webhooks->handle($gateway, $request);

        return $this->success($result, $result['handled'] ? 'Webhook processed.' : 'Webhook ignored.');
    }
}
