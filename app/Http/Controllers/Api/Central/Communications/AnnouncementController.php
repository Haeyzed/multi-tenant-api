<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Communications;

use App\Enums\Central\AnnouncementTarget;
use App\Enums\Central\AnnouncementType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Central\AnnouncementResource;
use App\Models\Central\Announcement;
use App\Services\Central\Communications\AnnouncementService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Central Announcements', description: 'Platform announcements.', weight: 190)]
final class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcementService,
    )
    {
    }

    #[Endpoint(operationId: 'communications.announcement.index', title: 'List announcements', description: 'Return a paginated list of announcements.')]
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('announcements.view'), 403);

        $announcements = $this->announcementService->paginate($request->only([
            'search', 'status', 'type', 'target', 'per_page',
        ]));

        return $this->paginated(
            AnnouncementResource::collection($announcements),
            'Announcements retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'communications.announcement.store', title: 'Create announcement', description: 'Create a new announcement and return it.')]
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('announcements.create'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'type' => ['required', Rule::enum(AnnouncementType::class)],
            'target' => ['sometimes', Rule::enum(AnnouncementTarget::class)],
            'is_dismissible' => ['sometimes', 'boolean'],
            'target_plan_ids' => ['sometimes', 'nullable', 'array'],
            'target_tenant_ids' => ['sometimes', 'nullable', 'array'],
            'regions' => ['sometimes', 'nullable', 'array'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $announcement = $this->announcementService->create($data, $request->user());

        return $this->success(new AnnouncementResource($announcement), 'Announcement created successfully.', 201);
    }

    #[Endpoint(operationId: 'communications.announcement.show', title: 'Show announcement', description: 'Return a single announcement by ID.')]
    public function show(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($request->user()?->can('announcements.view'), 403);
        $announcement->load('creator');

        return $this->success(new AnnouncementResource($announcement), 'Announcement retrieved successfully.');
    }

    #[Endpoint(operationId: 'communications.announcement.update', title: 'Update announcement', description: 'Update an existing announcement and return it.')]
    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($request->user()?->can('announcements.update'), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'type' => ['sometimes', Rule::enum(AnnouncementType::class)],
            'target' => ['sometimes', Rule::enum(AnnouncementTarget::class)],
            'is_dismissible' => ['sometimes', 'boolean'],
            'target_plan_ids' => ['sometimes', 'nullable', 'array'],
            'target_tenant_ids' => ['sometimes', 'nullable', 'array'],
            'regions' => ['sometimes', 'nullable', 'array'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $announcement = $this->announcementService->update($announcement, $data, $request->user());

        return $this->success(new AnnouncementResource($announcement), 'Announcement updated successfully.');
    }

    #[Endpoint(operationId: 'communications.announcement.publish', title: 'Publish announcement', description: 'Publish the announcement to make it visible.')]
    public function publish(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($request->user()?->can('announcements.publish'), 403);

        $announcement = $this->announcementService->publish($announcement, $request->user());

        return $this->success(new AnnouncementResource($announcement), 'Announcement published successfully.');
    }

    #[Endpoint(operationId: 'communications.announcement.schedule', title: 'Schedule', description: 'Schedule the resource for a future time.')]
    public function schedule(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($request->user()?->can('announcements.update'), 403);

        $data = $request->validate([
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
        ]);

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
        abort_unless($request->user()?->can('announcements.update'), 403);

        $announcement = $this->announcementService->archive($announcement, $request->user());

        return $this->success(new AnnouncementResource($announcement), 'Announcement archived successfully.');
    }

    #[Endpoint(operationId: 'communications.announcement.history', title: 'announcement history', description: 'Paginate history events for this announcement.')]
    public function history(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($request->user()?->can('announcements.view'), 403);

        $history = $this->announcementService->history($announcement, (int)$request->integer('per_page', 25));

        return $this->paginated($history, 'Announcement history retrieved successfully.');
    }

    #[Endpoint(operationId: 'communications.announcement.destroy', title: 'Delete announcement', description: 'Soft-delete or permanently remove a announcement.')]
    public function destroy(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($request->user()?->can('announcements.delete'), 403);
        $this->announcementService->delete($announcement);

        return $this->success(null, 'Announcement deleted successfully.');
    }
}

