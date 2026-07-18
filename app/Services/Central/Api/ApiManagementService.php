<?php

declare(strict_types=1);

namespace App\Services\Central\Api;

use App\Enums\Central\ApiKeyType;
use App\Enums\Central\WebhookEvent;
use App\Enums\Central\WebhookStatus;
use App\Models\Central\ApiClient;
use App\Models\Central\Webhook;
use App\Models\Central\WebhookDelivery;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Service responsible for central API client and webhook management.
 *
 * Encapsulates API client CRUD, secret rotation, webhook lifecycle,
 * event dispatch, and delivery retry logic so controllers remain thin.
 */
final class ApiManagementService
{
    /**
     * Paginate API clients with optional search filter.
     *
     * @param array{search?: string, per_page?: int} $filters
     * @return LengthAwarePaginator<int, ApiClient>
     */
    public function paginateClients(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return ApiClient::query()
            ->when(
                $filters['search'] ?? null,
                fn($q, string $search) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('client_id', 'like', "%{$search}%")
            )
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Create a new API client with a generated secret.
     *
     * @param array<string, mixed> $data
     * @param User|null $actor
     * @return array{client: ApiClient, plain_secret: string}
     */
    public function createClient(array $data, ?User $actor = null): array
    {
        $plainSecret = 'sec_' . Str::random(40);

        $client = ApiClient::query()->create([
            'name' => $data['name'],
            'client_id' => 'cli_' . Str::lower(Str::random(24)),
            'client_secret' => $plainSecret,
            'type' => $data['type'] ?? ApiKeyType::SERVICE->value,
            'scopes' => $data['scopes'] ?? [],
            'rate_limit_per_minute' => $data['rate_limit_per_minute'] ?? 60,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $actor?->id,
            'metadata' => $data['metadata'] ?? [],
        ]);

        return ['client' => $client, 'plain_secret' => $plainSecret];
    }

    /**
     * Update an existing API client.
     *
     * @param ApiClient $client
     * @param array<string, mixed> $data
     * @return ApiClient
     */
    public function updateClient(ApiClient $client, array $data): ApiClient
    {
        $client->update($data);

        return $client->refresh();
    }

    /**
     * Rotate an API client's secret and return the new plain-text value.
     *
     * @param ApiClient $client
     * @return array{client: ApiClient, plain_secret: string}
     */
    public function rotateSecret(ApiClient $client): array
    {
        $plainSecret = 'sec_' . Str::random(40);
        $client->update(['client_secret' => $plainSecret]);

        return ['client' => $client->refresh(), 'plain_secret' => $plainSecret];
    }

    /**
     * Delete an API client.
     *
     * @param ApiClient $client
     * @return void
     */
    public function deleteClient(ApiClient $client): void
    {
        $client->delete();
    }

    /**
     * Paginate webhooks with optional search filter.
     *
     * @param array{search?: string, per_page?: int} $filters
     * @return LengthAwarePaginator<int, Webhook>
     */
    public function paginateWebhooks(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return Webhook::query()
            ->withCount('deliveries')
            ->when(
                $filters['search'] ?? null,
                fn($q, string $search) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%")
            )
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Create a new webhook with a generated signing secret.
     *
     * @param array<string, mixed> $data
     * @param User|null $actor
     * @return array{webhook: Webhook, plain_secret: string}
     */
    public function createWebhook(array $data, ?User $actor = null): array
    {
        $plainSecret = 'whsec_' . Str::random(32);

        $webhook = Webhook::query()->create([
            'name' => $data['name'],
            'url' => $data['url'],
            'secret' => $plainSecret,
            'events' => $data['events'] ?? [],
            'is_active' => $data['is_active'] ?? true,
            'max_retries' => $data['max_retries'] ?? 3,
            'timeout_seconds' => $data['timeout_seconds'] ?? 10,
            'api_client_id' => $data['api_client_id'] ?? null,
            'created_by' => $actor?->id,
            'metadata' => $data['metadata'] ?? [],
        ]);

        return ['webhook' => $webhook, 'plain_secret' => $plainSecret];
    }

    /**
     * Update an existing webhook.
     *
     * @param Webhook $webhook
     * @param array<string, mixed> $data
     * @return Webhook
     */
    public function updateWebhook(Webhook $webhook, array $data): Webhook
    {
        $webhook->update($data);

        return $webhook->refresh();
    }

    /**
     * Delete a webhook.
     *
     * @param Webhook $webhook
     * @return void
     */
    public function deleteWebhook(Webhook $webhook): void
    {
        $webhook->delete();
    }

    /**
     * Dispatch a webhook event payload to the configured endpoint.
     *
     * @param Webhook $webhook
     * @param WebhookEvent|string $event
     * @param array<string, mixed> $payload
     * @return WebhookDelivery
     *
     * @throws ValidationException
     */
    public function dispatch(Webhook $webhook, WebhookEvent|string $event, array $payload = []): WebhookDelivery
    {
        if (!$webhook->is_active) {
            throw ValidationException::withMessages([
                'webhook' => ['Webhook is inactive.'],
            ]);
        }

        $eventValue = $event instanceof WebhookEvent ? $event->value : $event;

        if (!in_array($eventValue, $webhook->events ?? [], true)) {
            throw ValidationException::withMessages([
                'event' => ['Webhook is not subscribed to this event.'],
            ]);
        }

        return $this->deliver($webhook, $eventValue, $payload, 1);
    }

    /**
     * Execute an HTTP webhook delivery and persist the result.
     *
     * Creates a new delivery record or updates an existing one on retry.
     *
     * @param Webhook $webhook
     * @param string $event
     * @param array<string, mixed> $payload
     * @param int $attempt
     * @param WebhookDelivery|null $existing
     * @return WebhookDelivery
     */
    private function deliver(
        Webhook          $webhook,
        string           $event,
        array            $payload,
        int              $attempt,
        ?WebhookDelivery $existing = null,
    ): WebhookDelivery
    {
        return DB::transaction(function () use ($webhook, $event, $payload, $attempt, $existing): WebhookDelivery {
            $body = [
                'event' => $event,
                'data' => $payload,
                'sent_at' => now()->toIso8601String(),
            ];

            $delivery = $existing ?? WebhookDelivery::query()->create([
                'webhook_id' => $webhook->id,
                'event' => $event,
                'status' => WebhookStatus::PENDING,
                'attempt' => $attempt,
                'payload' => json_encode($body),
            ]);

            if ($existing) {
                $delivery->update([
                    'attempt' => $attempt,
                    'status' => WebhookStatus::RETRYING,
                    'payload' => json_encode($body),
                ]);
            }

            try {
                $response = Http::timeout($webhook->timeout_seconds)
                    ->withHeaders([
                        'X-Webhook-Signature' => hash_hmac('sha256', json_encode($body) ?: '', $webhook->secret),
                        'X-Webhook-Event' => $event,
                    ])
                    ->post($webhook->url, $body);

                $success = $response->successful();

                $delivery->update([
                    'status' => $success ? WebhookStatus::DELIVERED : WebhookStatus::FAILED,
                    'response_code' => $response->status(),
                    'response_body' => Str::limit($response->body(), 5000),
                    'delivered_at' => $success ? now() : null,
                    'error' => $success ? null : 'Non-success HTTP status',
                    'next_retry_at' => $success ? null : now()->addMinutes($attempt * 5),
                ]);
            } catch (Throwable $e) {
                $delivery->update([
                    'status' => WebhookStatus::FAILED,
                    'error' => $e->getMessage(),
                    'next_retry_at' => now()->addMinutes($attempt * 5),
                ]);
            }

            return $delivery->refresh();
        });
    }

    /**
     * Retry a failed webhook delivery.
     *
     * @param WebhookDelivery $delivery
     * @return WebhookDelivery
     *
     * @throws ValidationException
     */
    public function retryDelivery(WebhookDelivery $delivery): WebhookDelivery
    {
        $webhook = $delivery->webhook;

        if ($delivery->attempt >= $webhook->max_retries) {
            $delivery->update(['status' => WebhookStatus::EXCEEDED_RETRIES]);

            throw ValidationException::withMessages([
                'delivery' => ['Maximum retry attempts exceeded.'],
            ]);
        }

        $payload = json_decode((string)$delivery->payload, true) ?: [];

        return $this->deliver(
            $webhook,
            $delivery->event instanceof WebhookEvent ? $delivery->event->value : (string)$delivery->event,
            $payload,
            $delivery->attempt + 1,
            $delivery,
        );
    }

    /**
     * Paginate delivery logs for a webhook.
     *
     * @param Webhook $webhook
     * @param int $perPage
     * @return LengthAwarePaginator<int, WebhookDelivery>
     */
    public function deliveryLogs(Webhook $webhook, int $perPage = 25): LengthAwarePaginator
    {
        return $webhook->deliveries()->latest('id')->paginate(min($perPage, 100));
    }

    /**
     * List all available webhook event types for subscription UI.
     *
     * @return list<array{value: string, label: string, category: string}>
     */
    public function availableEvents(): array
    {
        return array_map(
            static fn(WebhookEvent $event): array => [
                'value' => $event->value,
                'label' => $event->label(),
                'category' => $event->category(),
            ],
            WebhookEvent::cases(),
        );
    }
}
