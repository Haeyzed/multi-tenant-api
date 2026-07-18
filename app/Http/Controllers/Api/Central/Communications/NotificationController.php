<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Communications;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\NotificationDeliveryResource;
use App\Http\Resources\Central\PlatformNotificationResource;
use App\Models\Central\NotificationDelivery;
use App\Models\Central\PlatformNotification;
use App\Services\Central\Communications\NotificationService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Notifications', description: 'Broadcast notifications and inbox.', weight: 180)]
final class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    )
    {
    }

    #[Endpoint(operationId: 'communications.notification.index', title: 'List notifications', description: 'Return a paginated list of notifications.')]
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('notifications.view'), 403);

        $notifications = $this->notificationService->paginate($request->only(['search', 'status', 'per_page']));

        return $this->paginated(
            PlatformNotificationResource::collection($notifications),
            'Notifications retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'communications.notification.store', title: 'Create notification', description: 'Create a new notification and return it.')]
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('notifications.create'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => ['string'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'target_user_ids' => ['sometimes', 'nullable', 'array'],
            'target_user_ids.*' => ['integer', 'exists:users,id'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $notification = $this->notificationService->create($data, $request->user());

        return $this->success(new PlatformNotificationResource($notification), 'Notification created successfully.', 201);
    }

    #[Endpoint(operationId: 'communications.notification.show', title: 'Show notification', description: 'Return a single notification by ID.')]
    public function show(Request $request, PlatformNotification $notification): JsonResponse
    {
        abort_unless($request->user()?->can('notifications.view'), 403);
        $notification->loadCount('deliveries')->load('creator');

        return $this->success(new PlatformNotificationResource($notification), 'Notification retrieved successfully.');
    }

    #[Endpoint(operationId: 'communications.notification.update', title: 'Update notification', description: 'Update an existing notification and return it.')]
    public function update(Request $request, PlatformNotification $notification): JsonResponse
    {
        abort_unless($request->user()?->can('notifications.update'), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => ['string'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'target_user_ids' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $notification = $this->notificationService->update($notification, $data);

        return $this->success(new PlatformNotificationResource($notification), 'Notification updated successfully.');
    }

    #[Endpoint(operationId: 'communications.notification.destroy', title: 'Delete notification', description: 'Soft-delete or permanently remove a notification.')]
    public function destroy(Request $request, PlatformNotification $notification): JsonResponse
    {
        abort_unless($request->user()?->can('notifications.delete'), 403);
        $this->notificationService->delete($notification);

        return $this->success(null, 'Notification deleted successfully.');
    }

    #[Endpoint(operationId: 'communications.notification.schedule', title: 'Schedule', description: 'Schedule the resource for a future time.')]
    public function schedule(Request $request, PlatformNotification $notification): JsonResponse
    {
        abort_unless($request->user()?->can('notifications.update'), 403);

        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $notification = $this->notificationService->schedule($notification, $data['scheduled_at']);

        return $this->success(new PlatformNotificationResource($notification), 'Notification scheduled successfully.');
    }

    #[Endpoint(operationId: 'communications.notification.broadcast', title: 'Broadcast notification', description: 'Deliver the notification to target users/channels.')]
    public function broadcast(Request $request, PlatformNotification $notification): JsonResponse
    {
        abort_unless($request->user()?->can('notifications.broadcast'), 403);

        $notification = $this->notificationService->broadcast($notification);

        return $this->success(new PlatformNotificationResource($notification), 'Notification broadcast successfully.');
    }

    #[Endpoint(operationId: 'communications.notification.cancel', title: 'Cancel notification', description: 'Cancel a scheduled, draft, or active notification.')]
    public function cancel(Request $request, PlatformNotification $notification): JsonResponse
    {
        abort_unless($request->user()?->can('notifications.update'), 403);

        $notification = $this->notificationService->cancel($notification);

        return $this->success(new PlatformNotificationResource($notification), 'Notification cancelled successfully.');
    }

    #[Endpoint(operationId: 'communications.notification.history', title: 'notification history', description: 'Paginate history events for this notification.')]
    public function history(Request $request, PlatformNotification $notification): JsonResponse
    {
        abort_unless($request->user()?->can('notifications.view'), 403);

        $history = $this->notificationService->history($notification, (int)$request->integer('per_page', 25));

        return $this->paginated(
            NotificationDeliveryResource::collection($history),
            'Notification history retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'communications.notification.inbox', title: 'Notification inbox', description: 'List in-app notification deliveries for the current user.')]
    public function inbox(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('notifications.inbox'), 403);

        $inbox = $this->notificationService->inbox($request->user(), $request->only(['status', 'unread', 'per_page']));

        return $this->paginated(
            NotificationDeliveryResource::collection($inbox),
            'Inbox retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'communications.notification.markRead', title: 'Mark as read', description: 'Mark a notification delivery as read.')]
    public function markRead(Request $request, NotificationDelivery $delivery): JsonResponse
    {
        abort_unless($request->user()?->can('notifications.inbox'), 403);
        abort_unless($delivery->user_id === $request->user()->id, 403);

        $delivery = $this->notificationService->markRead($delivery);

        return $this->success(new NotificationDeliveryResource($delivery), 'Notification marked as read.');
    }

    #[Endpoint(operationId: 'communications.notification.markUnread', title: 'Mark as unread', description: 'Mark a notification delivery as unread.')]
    public function markUnread(Request $request, NotificationDelivery $delivery): JsonResponse
    {
        abort_unless($request->user()?->can('notifications.inbox'), 403);
        abort_unless($delivery->user_id === $request->user()->id, 403);

        $delivery = $this->notificationService->markUnread($delivery);

        return $this->success(new NotificationDeliveryResource($delivery), 'Notification marked as unread.');
    }
}

