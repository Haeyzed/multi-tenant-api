<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Communications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Communications\ScheduleNotificationRequest;
use App\Http\Requests\Central\Communications\StoreNotificationRequest;
use App\Http\Requests\Central\Communications\UpdateNotificationRequest;
use App\Http\Resources\Central\NotificationDeliveryResource;
use App\Http\Resources\Central\PlatformNotificationResource;
use App\Models\Central\NotificationDelivery;
use App\Models\Central\PlatformNotification;
use App\Services\Central\Communications\NotificationService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Notifications', description: 'Broadcast notifications and inbox.', weight: 180)]
final class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    #[Endpoint(operationId: 'communications.notification.index', title: 'List notifications', description: 'Return a paginated list of notifications.')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewNotifications');

        $notifications = $this->notificationService->paginate($request->only(['search', 'status', 'per_page']));

        return $this->paginated(
            PlatformNotificationResource::collection($notifications),
            'Notifications retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'communications.notification.store', title: 'Create notification', description: 'Create a new notification and return it.')]
    public function store(StoreNotificationRequest $request): JsonResponse
    {
        $notification = $this->notificationService->create($request->validated(), $request->user());

        return $this->success(new PlatformNotificationResource($notification), 'Notification created successfully.', 201);
    }

    #[Endpoint(operationId: 'communications.notification.show', title: 'Show notification', description: 'Return a single notification by ID.')]
    public function show(PlatformNotification $notification): JsonResponse
    {
        $this->authorize('viewNotifications');
        $notification->loadCount('deliveries')->load('creator');

        return $this->success(new PlatformNotificationResource($notification), 'Notification retrieved successfully.');
    }

    #[Endpoint(operationId: 'communications.notification.update', title: 'Update notification', description: 'Update an existing notification and return it.')]
    public function update(UpdateNotificationRequest $request, PlatformNotification $notification): JsonResponse
    {
        $notification = $this->notificationService->update($notification, $request->validated());

        return $this->success(new PlatformNotificationResource($notification), 'Notification updated successfully.');
    }

    #[Endpoint(operationId: 'communications.notification.destroy', title: 'Delete notification', description: 'Soft-delete or permanently remove a notification.')]
    public function destroy(PlatformNotification $notification): JsonResponse
    {
        $this->authorize('deleteNotifications');
        $this->notificationService->delete($notification);

        return $this->success(null, 'Notification deleted successfully.');
    }

    #[Endpoint(operationId: 'communications.notification.schedule', title: 'Schedule', description: 'Schedule the resource for a future time.')]
    public function schedule(ScheduleNotificationRequest $request, PlatformNotification $notification): JsonResponse
    {
        $data = $request->validated();

        $notification = $this->notificationService->schedule($notification, $data['scheduled_at']);

        return $this->success(new PlatformNotificationResource($notification), 'Notification scheduled successfully.');
    }

    #[Endpoint(operationId: 'communications.notification.broadcast', title: 'Broadcast notification', description: 'Deliver the notification to target users/channels.')]
    public function broadcast(Request $request, PlatformNotification $notification): JsonResponse
    {
        $this->authorize('broadcastNotifications');

        $notification = $this->notificationService->broadcast($notification);

        return $this->success(new PlatformNotificationResource($notification), 'Notification broadcast successfully.');
    }

    #[Endpoint(operationId: 'communications.notification.cancel', title: 'Cancel notification', description: 'Cancel a scheduled, draft, or active notification.')]
    public function cancel(Request $request, PlatformNotification $notification): JsonResponse
    {
        $this->authorize('updateNotifications');

        $notification = $this->notificationService->cancel($notification);

        return $this->success(new PlatformNotificationResource($notification), 'Notification cancelled successfully.');
    }

    #[Endpoint(operationId: 'communications.notification.history', title: 'notification history', description: 'Paginate history events for this notification.')]
    public function history(Request $request, PlatformNotification $notification): JsonResponse
    {
        $this->authorize('viewNotifications');

        $history = $this->notificationService->history($notification, (int) $request->integer('per_page', 25));

        return $this->paginated(
            NotificationDeliveryResource::collection($history),
            'Notification history retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'communications.notification.inbox', title: 'Notification inbox', description: 'List in-app notification deliveries for the current user.')]
    public function inbox(Request $request): JsonResponse
    {
        $this->authorize('inboxNotifications');

        $inbox = $this->notificationService->inbox($request->user(), $request->only(['status', 'unread', 'per_page']));

        return $this->paginated(
            NotificationDeliveryResource::collection($inbox),
            'Inbox retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'communications.notification.markRead', title: 'Mark as read', description: 'Mark a notification delivery as read.')]
    public function markRead(Request $request, NotificationDelivery $delivery): JsonResponse
    {
        $this->authorize('inboxNotifications');
        abort_unless($delivery->user_id === $request->user()->id, 403);

        $delivery = $this->notificationService->markRead($delivery);

        return $this->success(new NotificationDeliveryResource($delivery), 'Notification marked as read.');
    }

    #[Endpoint(operationId: 'communications.notification.markUnread', title: 'Mark as unread', description: 'Mark a notification delivery as unread.')]
    public function markUnread(Request $request, NotificationDelivery $delivery): JsonResponse
    {
        $this->authorize('inboxNotifications');
        abort_unless($delivery->user_id === $request->user()->id, 403);

        $delivery = $this->notificationService->markUnread($delivery);

        return $this->success(new NotificationDeliveryResource($delivery), 'Notification marked as unread.');
    }
}
