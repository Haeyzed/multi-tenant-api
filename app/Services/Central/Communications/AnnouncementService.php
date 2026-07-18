<?php

declare(strict_types=1);

namespace App\Services\Central\Communications;

use App\Enums\Central\AnnouncementStatus;
use App\Models\Central\Announcement;
use App\Models\Central\AnnouncementHistory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service responsible for platform announcement management.
 *
 * Encapsulates announcement CRUD, publishing, scheduling, archival,
 * and history tracking so controllers remain thin.
 */
final class AnnouncementService
{
    /**
     * Create a new platform announcement.
     *
     * @param array<string, mixed> $data
     * @param User|null $actor
     * @return Announcement
     */
    public function create(array $data, ?User $actor = null): Announcement
    {
        return DB::transaction(function () use ($data, $actor): Announcement {
            $announcement = Announcement::query()->create([
                'title' => $data['title'],
                'body' => $data['body'],
                'type' => $data['type'],
                'target' => $data['target'] ?? 'all_tenants',
                'status' => isset($data['starts_at']) && now()->lt($data['starts_at'])
                    ? AnnouncementStatus::Scheduled
                    : AnnouncementStatus::Draft,
                'is_dismissible' => $data['is_dismissible'] ?? true,
                'target_plan_ids' => $data['target_plan_ids'] ?? null,
                'target_tenant_ids' => $data['target_tenant_ids'] ?? null,
                'regions' => $data['regions'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'created_by' => $actor?->id,
                'metadata' => $data['metadata'] ?? [],
            ]);

            $this->recordHistory($announcement, 'created', $actor);

            return $announcement;
        });
    }

    /**
     * Record an announcement history entry for audit purposes.
     *
     * @param Announcement $announcement
     * @param string $action
     * @param User|null $actor
     * @param array<string, mixed>|null $properties
     * @return void
     */
    private function recordHistory(Announcement $announcement, string $action, ?User $actor, ?array $properties = null): void
    {
        AnnouncementHistory::query()->create([
            'announcement_id' => $announcement->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'properties' => $properties,
        ]);
    }

    /**
     * Publish an announcement and set its start timestamp.
     *
     * @param Announcement $announcement
     * @param User|null $actor
     * @return Announcement
     */
    public function publish(Announcement $announcement, ?User $actor = null): Announcement
    {
        $announcement->update([
            'status' => AnnouncementStatus::Published,
            'published_at' => now(),
            'starts_at' => $announcement->starts_at ?? now(),
        ]);

        $this->recordHistory($announcement, 'published', $actor);

        return $announcement->refresh();
    }

    /**
     * Update an existing announcement.
     *
     * @param Announcement $announcement
     * @param array<string, mixed> $data
     * @param User|null $actor
     * @return Announcement
     */
    public function update(Announcement $announcement, array $data, ?User $actor = null): Announcement
    {
        $announcement->update($data);
        $this->recordHistory($announcement, 'updated', $actor, $data);

        return $announcement->refresh();
    }

    /**
     * Schedule an announcement for future publication.
     *
     * @param Announcement $announcement
     * @param string $startsAt
     * @param string|null $endsAt
     * @param User|null $actor
     * @return Announcement
     */
    public function schedule(Announcement $announcement, string $startsAt, ?string $endsAt = null, ?User $actor = null): Announcement
    {
        $announcement->update([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => AnnouncementStatus::Scheduled,
        ]);

        $this->recordHistory($announcement, 'scheduled', $actor, [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        return $announcement->refresh();
    }

    /**
     * Archive a published or scheduled announcement.
     *
     * @param Announcement $announcement
     * @param User|null $actor
     * @return Announcement
     */
    public function archive(Announcement $announcement, ?User $actor = null): Announcement
    {
        $announcement->update(['status' => AnnouncementStatus::Archived]);
        $this->recordHistory($announcement, 'archived', $actor);

        return $announcement->refresh();
    }

    /**
     * Mark an announcement as expired.
     *
     * @param Announcement $announcement
     * @param User|null $actor
     * @return Announcement
     */
    public function expire(Announcement $announcement, ?User $actor = null): Announcement
    {
        $announcement->update(['status' => AnnouncementStatus::Expired]);
        $this->recordHistory($announcement, 'expired', $actor);

        return $announcement->refresh();
    }

    /**
     * Paginate history entries for an announcement.
     *
     * @param Announcement $announcement
     * @param int $perPage
     * @return LengthAwarePaginator<int, AnnouncementHistory>
     */
    public function history(Announcement $announcement, int $perPage = 25): LengthAwarePaginator
    {
        return $announcement->histories()
            ->with('user:id,name,email')
            ->latest('id')
            ->paginate(min($perPage, 100));
    }

    /**
     * Paginate announcements with optional filters.
     *
     * @param array{search?: string, status?: string, type?: string, target?: string, per_page?: int} $filters
     * @return LengthAwarePaginator<int, Announcement>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return Announcement::query()
            ->with('creator:id,name,email')
            ->when(
                $filters['search'] ?? null,
                fn($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%");
                })
            )
            ->when($filters['status'] ?? null, fn($q, string $status) => $q->where('status', $status))
            ->when($filters['type'] ?? null, fn($q, string $type) => $q->where('type', $type))
            ->when($filters['target'] ?? null, fn($q, string $target) => $q->where('target', $target))
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Delete a non-published announcement.
     *
     * @param Announcement $announcement
     * @return void
     *
     * @throws ValidationException
     */
    public function delete(Announcement $announcement): void
    {
        if ($announcement->status === AnnouncementStatus::Published) {
            throw ValidationException::withMessages([
                'announcement' => ['Published announcements should be archived instead of deleted.'],
            ]);
        }

        $announcement->delete();
    }
}
