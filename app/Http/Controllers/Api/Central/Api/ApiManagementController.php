<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Api;

use App\Enums\Central\ApiKeyType;
use App\Http\Controllers\Controller;
use App\Models\Central\ApiClient;
use App\Models\Central\Webhook;
use App\Models\Central\WebhookDelivery;
use App\Services\Central\Api\ApiManagementService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Central API Clients', description: 'API clients, secrets, scopes, rate limits.', weight: 220)]
final class ApiManagementController extends Controller
{
    public function __construct(
        private readonly ApiManagementService $apiManagementService,
    ) {}

    #[Endpoint(operationId: 'api.apimanagement.clients', title: 'List API clients', description: 'Paginate registered API clients (secrets omitted).')]
    public function clients(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('api.clients.view'), 403);

        $clients = $this->apiManagementService->paginateClients($request->only(['search', 'per_page']));

        return $this->paginated($clients, 'API clients retrieved successfully.');
    }

    #[Endpoint(operationId: 'api.apimanagement.storeClient', title: 'Create API client', description: 'Create a client and return the plaintext secret once.')]
    public function storeClient(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('api.clients.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(ApiKeyType::class)],
            'scopes' => ['sometimes', 'array'],
            'rate_limit_per_minute' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $result = $this->apiManagementService->createClient($data, $request->user());

        return $this->success([
            'client' => $result['client'],
            'client_secret' => $result['plain_secret'],
        ], 'API client created successfully.', 201);
    }

    #[Endpoint(operationId: 'api.apimanagement.showClient', title: 'Show API client', description: 'Return an API client (secret omitted).')]
    public function showClient(Request $request, ApiClient $apiClient): JsonResponse
    {
        abort_unless($request->user()?->can('api.clients.view'), 403);

        return $this->success($apiClient, 'API client retrieved successfully.');
    }

    #[Endpoint(operationId: 'api.apimanagement.updateClient', title: 'Update API client', description: 'Update client scopes, limits, or active state.')]
    public function updateClient(Request $request, ApiClient $apiClient): JsonResponse
    {
        abort_unless($request->user()?->can('api.clients.manage'), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'scopes' => ['sometimes', 'array'],
            'rate_limit_per_minute' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ]);

        return $this->success(
            $this->apiManagementService->updateClient($apiClient, $data),
            'API client updated successfully.',
        );
    }

    #[Endpoint(operationId: 'api.apimanagement.rotateSecret', title: 'Rotate client secret', description: 'Rotate and return a new plaintext client secret once.')]
    public function rotateSecret(Request $request, ApiClient $apiClient): JsonResponse
    {
        abort_unless($request->user()?->can('api.clients.manage'), 403);

        $result = $this->apiManagementService->rotateSecret($apiClient);

        return $this->success([
            'client' => $result['client'],
            'client_secret' => $result['plain_secret'],
        ], 'API client secret rotated successfully.');
    }

    #[Endpoint(operationId: 'api.apimanagement.destroyClient', title: 'Delete API client', description: 'Soft-delete an API client.')]
    public function destroyClient(Request $request, ApiClient $apiClient): JsonResponse
    {
        abort_unless($request->user()?->can('api.clients.manage'), 403);
        $this->apiManagementService->deleteClient($apiClient);

        return $this->success(null, 'API client deleted successfully.');
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.webhooks', title: 'List webhooks', description: 'Paginate outbound webhook endpoints.')]
    public function webhooks(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('api.webhooks.view'), 403);

        return $this->paginated(
            $this->apiManagementService->paginateWebhooks($request->only(['search', 'per_page'])),
            'Webhooks retrieved successfully.',
        );
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.storeWebhook', title: 'Create webhook', description: 'Create a webhook and return the signing secret once.')]
    public function storeWebhook(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('api.webhooks.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string'],
            'is_active' => ['sometimes', 'boolean'],
            'max_retries' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'api_client_id' => ['sometimes', 'nullable', 'integer', 'exists:api_clients,id'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $result = $this->apiManagementService->createWebhook($data, $request->user());

        return $this->success([
            'webhook' => $result['webhook'],
            'secret' => $result['plain_secret'],
        ], 'Webhook created successfully.', 201);
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.showWebhook', title: 'Show webhook', description: 'Return webhook configuration (secret omitted).')]
    public function showWebhook(Request $request, Webhook $webhook): JsonResponse
    {
        abort_unless($request->user()?->can('api.webhooks.view'), 403);
        $webhook->loadCount('deliveries');

        return $this->success($webhook, 'Webhook retrieved successfully.');
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.updateWebhook', title: 'Update webhook', description: 'Update webhook URL, events, or retry settings.')]
    public function updateWebhook(Request $request, Webhook $webhook): JsonResponse
    {
        abort_unless($request->user()?->can('api.webhooks.manage'), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url', 'max:2048'],
            'events' => ['sometimes', 'array', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'max_retries' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'metadata' => ['sometimes', 'array'],
        ]);

        return $this->success(
            $this->apiManagementService->updateWebhook($webhook, $data),
            'Webhook updated successfully.',
        );
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.destroyWebhook', title: 'Delete webhook', description: 'Soft-delete a webhook endpoint.')]
    public function destroyWebhook(Request $request, Webhook $webhook): JsonResponse
    {
        abort_unless($request->user()?->can('api.webhooks.manage'), 403);
        $this->apiManagementService->deleteWebhook($webhook);

        return $this->success(null, 'Webhook deleted successfully.');
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.dispatchWebhook', title: 'Dispatch webhook', description: 'Send a test/manual event delivery to the webhook URL.')]
    public function dispatchWebhook(Request $request, Webhook $webhook): JsonResponse
    {
        abort_unless($request->user()?->can('api.webhooks.manage'), 403);

        $data = $request->validate([
            'event' => ['required', 'string'],
            'payload' => ['sometimes', 'array'],
        ]);

        $delivery = $this->apiManagementService->dispatch($webhook, $data['event'], $data['payload'] ?? []);

        return $this->success($delivery, 'Webhook dispatched.', 201);
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.webhookLogs', title: 'Webhook deliveries', description: 'Paginate delivery attempts for a webhook.')]
    public function webhookLogs(Request $request, Webhook $webhook): JsonResponse
    {
        abort_unless($request->user()?->can('api.webhooks.view'), 403);

        return $this->paginated(
            $this->apiManagementService->deliveryLogs($webhook, (int) $request->integer('per_page', 25)),
            'Webhook delivery logs retrieved successfully.',
        );
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.retryDelivery', title: 'Retry delivery', description: 'Retry a failed webhook delivery attempt.')]
    public function retryDelivery(Request $request, WebhookDelivery $delivery): JsonResponse
    {
        abort_unless($request->user()?->can('api.webhooks.manage'), 403);

        return $this->success(
            $this->apiManagementService->retryDelivery($delivery),
            'Webhook delivery retried.',
        );
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.events', title: 'Webhook events catalog', description: 'List all supported webhook event names.')]
    public function events(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('api.webhooks.view'), 403);

        return $this->success(
            $this->apiManagementService->availableEvents(),
            'Webhook events retrieved successfully.',
        );
    }
}

