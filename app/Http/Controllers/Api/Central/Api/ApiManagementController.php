<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\DispatchWebhookRequest;
use App\Http\Requests\Central\Api\StoreApiClientRequest;
use App\Http\Requests\Central\Api\StoreWebhookRequest;
use App\Http\Requests\Central\Api\UpdateApiClientRequest;
use App\Http\Requests\Central\Api\UpdateWebhookRequest;
use App\Models\Central\ApiClient;
use App\Models\Central\Webhook;
use App\Models\Central\WebhookDelivery;
use App\Services\Central\Api\ApiManagementService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central API Clients', description: 'API clients, secrets, scopes, rate limits.', weight: 220)]
final class ApiManagementController extends Controller
{
    public function __construct(
        private readonly ApiManagementService $apiManagementService,
    ) {}

    #[Endpoint(operationId: 'api.apimanagement.clients', title: 'List API clients', description: 'Paginate registered API clients (secrets omitted).')]
    public function clients(Request $request): JsonResponse
    {
        $this->authorize('viewApiClients');

        $clients = $this->apiManagementService->paginateClients($request->only(['search', 'per_page']));

        return $this->paginated($clients, 'API clients retrieved successfully.');
    }

    #[Endpoint(operationId: 'api.apimanagement.storeClient', title: 'Create API client', description: 'Create a client and return the plaintext secret once.')]
    public function storeClient(StoreApiClientRequest $request): JsonResponse
    {
        $result = $this->apiManagementService->createClient($request->validated(), $request->user());

        return $this->success([
            'client' => $result['client'],
            'client_secret' => $result['plain_secret'],
        ], 'API client created successfully.', 201);
    }

    #[Endpoint(operationId: 'api.apimanagement.showClient', title: 'Show API client', description: 'Return an API client (secret omitted).')]
    public function showClient(ApiClient $apiClient): JsonResponse
    {
        $this->authorize('viewApiClients');

        return $this->success($apiClient, 'API client retrieved successfully.');
    }

    #[Endpoint(operationId: 'api.apimanagement.updateClient', title: 'Update API client', description: 'Update client scopes, limits, or active state.')]
    public function updateClient(UpdateApiClientRequest $request, ApiClient $apiClient): JsonResponse
    {
        return $this->success(
            $this->apiManagementService->updateClient($apiClient, $request->validated()),
            'API client updated successfully.',
        );
    }

    #[Endpoint(operationId: 'api.apimanagement.rotateSecret', title: 'Rotate client secret', description: 'Rotate and return a new plaintext client secret once.')]
    public function rotateSecret(Request $request, ApiClient $apiClient): JsonResponse
    {
        $this->authorize('manageApiClients');

        $result = $this->apiManagementService->rotateSecret($apiClient);

        return $this->success([
            'client' => $result['client'],
            'client_secret' => $result['plain_secret'],
        ], 'API client secret rotated successfully.');
    }

    #[Endpoint(operationId: 'api.apimanagement.destroyClient', title: 'Delete API client', description: 'Soft-delete an API client.')]
    public function destroyClient(Request $request, ApiClient $apiClient): JsonResponse
    {
        $this->authorize('manageApiClients');
        $this->apiManagementService->deleteClient($apiClient);

        return $this->success(null, 'API client deleted successfully.');
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.webhooks', title: 'List webhooks', description: 'Paginate outbound webhook endpoints.')]
    public function webhooks(Request $request): JsonResponse
    {
        $this->authorize('viewApiWebhooks');

        return $this->paginated(
            $this->apiManagementService->paginateWebhooks($request->only(['search', 'per_page'])),
            'Webhooks retrieved successfully.',
        );
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.storeWebhook', title: 'Create webhook', description: 'Create a webhook and return the signing secret once.')]
    public function storeWebhook(StoreWebhookRequest $request): JsonResponse
    {
        $result = $this->apiManagementService->createWebhook($request->validated(), $request->user());

        return $this->success([
            'webhook' => $result['webhook'],
            'secret' => $result['plain_secret'],
        ], 'Webhook created successfully.', 201);
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.showWebhook', title: 'Show webhook', description: 'Return webhook configuration (secret omitted).')]
    public function showWebhook(Webhook $webhook): JsonResponse
    {
        $this->authorize('viewApiWebhooks');
        $webhook->loadCount('deliveries');

        return $this->success($webhook, 'Webhook retrieved successfully.');
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.updateWebhook', title: 'Update webhook', description: 'Update webhook URL, events, or retry settings.')]
    public function updateWebhook(UpdateWebhookRequest $request, Webhook $webhook): JsonResponse
    {
        return $this->success(
            $this->apiManagementService->updateWebhook($webhook, $request->validated()),
            'Webhook updated successfully.',
        );
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.destroyWebhook', title: 'Delete webhook', description: 'Soft-delete a webhook endpoint.')]
    public function destroyWebhook(Request $request, Webhook $webhook): JsonResponse
    {
        $this->authorize('manageApiWebhooks');
        $this->apiManagementService->deleteWebhook($webhook);

        return $this->success(null, 'Webhook deleted successfully.');
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.dispatchWebhook', title: 'Dispatch webhook', description: 'Send a test/manual event delivery to the webhook URL.')]
    public function dispatchWebhook(DispatchWebhookRequest $request, Webhook $webhook): JsonResponse
    {
        $data = $request->validated();

        $delivery = $this->apiManagementService->dispatch($webhook, $data['event'], $data['payload'] ?? []);

        return $this->success($delivery, 'Webhook dispatched.', 201);
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.webhookLogs', title: 'Webhook deliveries', description: 'Paginate delivery attempts for a webhook.')]
    public function webhookLogs(Request $request, Webhook $webhook): JsonResponse
    {
        $this->authorize('viewApiWebhooks');

        return $this->paginated(
            $this->apiManagementService->deliveryLogs($webhook, (int) $request->integer('per_page', 25)),
            'Webhook delivery logs retrieved successfully.',
        );
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.retryDelivery', title: 'Retry delivery', description: 'Retry a failed webhook delivery attempt.')]
    public function retryDelivery(Request $request, WebhookDelivery $delivery): JsonResponse
    {
        $this->authorize('manageApiWebhooks');

        return $this->success(
            $this->apiManagementService->retryDelivery($delivery),
            'Webhook delivery retried.',
        );
    }

    #[Group('Central Webhooks', description: 'Outbound webhooks, delivery logs, retries.', weight: 230)]
    #[Endpoint(operationId: 'api.apimanagement.events', title: 'Webhook events catalog', description: 'List all supported webhook event names.')]
    public function events(Request $request): JsonResponse
    {
        $this->authorize('viewApiWebhooks');

        return $this->success(
            $this->apiManagementService->availableEvents(),
            'Webhook events retrieved successfully.',
        );
    }
}
