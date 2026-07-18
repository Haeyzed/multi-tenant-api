<?php

declare(strict_types=1);

namespace App\Services\Central\Communications;

use App\Enums\Central\DeliveryStatus;
use App\Enums\Central\NotificationChannel;
use App\Enums\Central\NotificationStatus;
use App\Models\Central\NotificationDelivery;
use App\Models\Central\PlatformNotification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service responsible for platform notification management.
 *
 * Encapsulates notification CRUD, scheduling, broadcasting, inbox queries,
 * and delivery tracking so controllers remain thin.
 */
final class NotificationService
{
    /**
     * Schedule a notification for future delivery.
     *
     * @param PlatformNotification $notification
     * @param string $scheduledAt
     * @return PlatformNotification
     */
    public function schedule(PlatformNotification $notification, string $scheduledAt): PlatformNotification
    {
        $notification->update([
            'scheduled_at' => $scheduledAt,
            'status' => NotificationStatus::Scheduled,
        ]);

        return $notification->refresh();
    }

    /**
     * Update a draft or scheduled notification.
     *
     * @param PlatformNotification $notification
     * @param array<string, mixed> $data
     * @return PlatformNotification
     *
     * @throws ValidationException
     */
    public function update(PlatformNotification $notification, array $data): PlatformNotification
    {
        if (in_array($notification->status, [NotificationStatus::Sent, NotificationStatus::Sending], true)) {
            throw ValidationException::withMessages([
                'notification' => ['Sent notifications cannot be edited.'],
            ]);
        }

        if (array_key_exists('scheduled_at', $data) && $data['scheduled_at']) {
            $data['status'] = NotificationStatus::Scheduled->value;
        }

        $notification->update($data);

        return $notification->refresh();
    }

    /**
     * Broadcast a notification to target users across configured channels.
     *
     * Creates delivery records for each user/channel combination and marks
     * the notification as sent.
     *
     * @param PlatformNotification $notification
     * @return PlatformNotification
     */
    public function broadcast(PlatformNotification $notification): PlatformNotification
    {
        return DB::transaction(function () use ($notification): PlatformNotification {
            $notification->update(['status' => NotificationStatus::Sending]);

            $userIds = $notification->target_user_ids;

            $users = empty($userIds)
                ? User::query()->whereNull('deleted_at')->get(['id'])
                : User::query()->whereIn('id', $userIds)->get(['id']);

            foreach ($users as $user) {
                foreach ($notification->channels as $channel) {
                    NotificationDelivery::query()->create([
                        'platform_notification_id' => $notification->id,
                        'user_id' => $user->id,
                        'channel' => $channel,
                        'status' => DeliveryStatus::Delivered,
                        'delivered_at' => now(),
                    ]);
                }
            }

            $notification->update([
                'status' => NotificationStatus::Sent,
                'sent_at' => now(),
            ]);

            activity()->performedOn($notification)->log('notification.broadcast');

            return $notification->refresh()->loadCount('deliveries');
        });
    }

    /**
     * Create a new platform notification.
     *
     * @param array<string, mixed> $data
     * @param User|null $actor
     * @return PlatformNotification
     */
    public function create(array $data, ?User $actor = null): PlatformNotification
    {
        $notification = PlatformNotification::query()->create([
            'title' => $data['title'],
            'body' => $data['body'],
            'channels' => $data['channels'] ?? [NotificationChannel::IN_APP->value],
            'status' => isset($data['scheduled_at'])
                ? NotificationStatus::Scheduled
                : NotificationStatus::Draft,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'created_by' => $actor?->id,
            'target_user_ids' => $data['target_user_ids'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);

        activity()->performedOn($notification)->causedBy($actor)->log('notification.created');

        return $notification;
    }

    /**
     * Cancel a draft or scheduled notification.
     *
     * @param PlatformNotification $notification
     * @return PlatformNotification
     *
     * @throws ValidationException
     */
    public function cancel(PlatformNotification $notification): PlatformNotification
    {
        if ($notification->status === NotificationStatus::Sent) {
            throw ValidationException::withMessages([
                'notification' => ['Sent notifications cannot be cancelled.'],
            ]);
        }

        $notification->update(['status' => NotificationStatus::Cancelled]);

        return $notification->refresh();
    }

    /**
     * Paginate in-app notification deliveries for a user's inbox.
     *
     * @param User $user
     * @param array{status?: string, unread?: bool, per_page?: int} $filters
     * @return LengthAwarePaginator<int, NotificationDelivery>
     */
    public function inbox(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return NotificationDelivery::query()
            ->with('notification')
            ->where('user_id', $user->id)
            ->where('channel', NotificationChannel::IN_APP)
            ->when(
                ($filters['unread'] ?? null) === true || ($filters['unread'] ?? null) === '1',
                fn($query) => $query->whereNull('read_at')
            )
            ->when(
                $filters['status'] ?? null,
                fn($query, string $status) => $query->where('status', $status)
            )
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Paginate platform notifications with optional filters.
     *
     * @param array{search?: string, status?: string, per_page?: int} $filters
     * @return LengthAwarePaginator<int, PlatformNotification>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return PlatformNotification::query()
            ->withCount('deliveries')
            ->with('creator:id,name,email')
            ->when(
                $filters['search'] ?? null,
                fn($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%");
                })
            )
            ->when(
                $filters['status'] ?? null,
                fn($query, string $status) => $query->where('status', $status)
            )
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Mark a notification delivery as read.
     *
     * @param NotificationDelivery $delivery
     * @return NotificationDelivery
     */
    public function markRead(NotificationDelivery $delivery): NotificationDelivery
    {
        $delivery->update([
            'read_at' => now(),
            'status' => DeliveryStatus::Read,
        ]);

        return $delivery->refresh();
    }

    /**
     * Mark a notification delivery as unread.
     *
     * @param NotificationDelivery $delivery
     * @return NotificationDelivery
     */
    public function markUnread(NotificationDelivery $delivery): NotificationDelivery
    {
        $delivery->update([
            'read_at' => null,
            'status' => DeliveryStatus::Delivered,
        ]);

        return $delivery->refresh();
    }

    /**
     * Paginate delivery history for a platform notification.
     *
     * @param PlatformNotification $notification
     * @param int $perPage
     * @return LengthAwarePaginator<int, NotificationDelivery>
     */
    public function history(PlatformNotification $notification, int $perPage = 25): LengthAwarePaginator
    {
        return $notification->deliveries()
            ->with('user:id,name,email')
            ->latest('id')
            ->paginate(min($perPage, 100));
    }

    /**
     * Delete a platform notification.
     *
     * @param PlatformNotification $notification
     * @return void
     */
    public function delete(PlatformNotification $notification): void
    {
        $notification->delete();
    }
}
