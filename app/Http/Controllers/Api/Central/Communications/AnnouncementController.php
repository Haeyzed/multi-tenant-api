<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Communications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Communications\ScheduleAnnouncementRequest;
use App\Http\Requests\Central\Communications\StoreAnnouncementRequest;
use App\Http\Requests\Central\Communications\UpdateAnnouncementRequest;
use App\Http\Resources\Central\AnnouncementResource;
use App\Models\Central\Announcement;
use App\Services\Central\Communications\AnnouncementService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Announcements', description: 'Platform announcements.', weight: 190)]
final class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcementService,
    ) {}

    #[Endpoint(operationId: 'communications.announcement.index', title: 'List announcements', description: 'Return a paginated list of announcements.')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAnnouncements');

        $announcements = $this->announcementService->paginate($request->only([
            'search', 'status', 'type', 'target', 'per_page',
        ]));

        return $this->paginated(
            AnnouncementResource::collection($announcements),
            'Announcements retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'communications.announcement.store', title: 'Create announcement', description: 'Create a new announcement and return it.')]
    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $announcement = $this->announcementService->create($request->validated(), $request->user());

        return $this->success(new AnnouncementResource($announcement), 'Announcement created successfully.', 201);
    }

    #[Endpoint(operationId: 'communications.announcement.show', title: 'Show announcement', description: 'Return a single announcement by ID.')]
    public function show(Announcement $announcement): JsonResponse
    {
        $this->authorize('viewAnnouncements');
        $announcement->load('creator');

        return $this->success(new AnnouncementResource($announcement), 'Announcement retrieved successfully.');
    }

    #[Endpoint(operationId: 'communications.announcement.update', title: 'Update announcement', description: 'Update an existing announcement and return it.')]
    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $announcement = $this->announcementService->update($announcement, $request->validated(), $request->user());

        return $this->success(new AnnouncementResource($announcement), 'Announcement updated successfully.');
    }

    #[Endpoint(operationId: 'communications.announcement.publish', title: 'Publish announcement', description: 'Publish the announcement to make it visible.')]
    public function publish(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('publishAnnouncements');

        $announcement = $this->announcementService->publish($announcement, $request->user());

        return $this->success(new AnnouncementResource($announcement), 'Announcement published successfully.');
    }

    #[Endpoint(operationId: 'communications.announcement.schedule', title: 'Schedule', description: 'Schedule the resource for a future time.')]
    public function schedule(ScheduleAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $data = $request->validated();

        $announcement = $this->announcementService->schedule(
            $announcement,
            $data['starts_at'],
            $data['ends_at'] ?? null,
            $request->user(),
        );

        return $this->success(new AnnouncementResource($announcement), 'Announcement scheduled successfully.');
    }

    #[Endpoint(operationId: 'communications.announcement.archive', title: 'Archive announcement', description: 'Archive the announcement.')]
    public function archive(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('updateAnnouncements');

        $announcement = $this->announcementService->archive($announcement, $request->user());

        return $this->success(new AnnouncementResource($announcement), 'Announcement archived successfully.');
    }

    #[Endpoint(operationId: 'communications.announcement.history', title: 'announcement history', description: 'Paginate history events for this announcement.')]
    public function history(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('viewAnnouncements');

        $history = $this->announcementService->history($announcement, (int) $request->integer('per_page', 25));

        return $this->paginated($history, 'Announcement history retrieved successfully.');
    }

    #[Endpoint(operationId: 'communications.announcement.destroy', title: 'Delete announcement', description: 'Soft-delete or permanently remove a announcement.')]
    public function destroy(Announcement $announcement): JsonResponse
    {
        $this->authorize('deleteAnnouncements');
        $this->announcementService->delete($announcement);

        return $this->success(null, 'Announcement deleted successfully.');
    }
}
