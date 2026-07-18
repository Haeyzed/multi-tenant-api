<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Users;

use App\Enums\Central\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\BulkActivateUsersRequest;
use App\Http\Requests\Central\Users\BulkDeleteUsersRequest;
use App\Http\Requests\Central\Users\BulkSuspendUsersRequest;
use App\Http\Requests\Central\Users\ResetUserPasswordRequest;
use App\Http\Requests\Central\Users\StoreUserRequest;
use App\Http\Requests\Central\Users\SyncUserPermissionsRequest;
use App\Http\Requests\Central\Users\SyncUserRolesRequest;
use App\Http\Requests\Central\Users\UpdateUserRequest;
use App\Http\Requests\Central\Users\UpdateUserStatusRequest;
use App\Http\Requests\Central\Users\UploadUserAvatarRequest;
use App\Http\Resources\Central\ActivityResource;
use App\Http\Resources\Central\UserResource;
use App\Models\User;
use App\Services\Central\Users\UserService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Users', description: 'Central user administration.', weight: 80)]
final class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    )
    {
    }

    #[Endpoint(operationId: 'users.user.index', title: 'List users', description: 'Return a paginated list of users.')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = $this->userService->paginate($request->only([
            'search', 'status', 'role', 'trashed', 'per_page',
        ]));

        return $this->paginated(UserResource::collection($users), 'Users retrieved successfully.');
    }

    #[Endpoint(operationId: 'users.user.statistics', title: 'User statistics', description: 'Return aggregate statistics for the users index.')]
    public function statistics(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        return $this->success(
            $this->userService->overviewStatistics(),
            'User statistics retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'users.user.store', title: 'Create user', description: 'Create a new user and return it.')]
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        return $this->success(new UserResource($user), 'User created successfully.', 201);
    }

    #[Endpoint(operationId: 'users.user.show', title: 'Show record', description: 'Return a single record by ID.')]
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);
        $user->load(['roles', 'permissions', 'media']);

        return $this->success(new UserResource($user), 'User retrieved successfully.');
    }

    #[Endpoint(operationId: 'users.user.update', title: 'Update user', description: 'Update an existing user and return it.')]
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user = $this->userService->update($user, $request->validated());

        return $this->success(new UserResource($user), 'User updated successfully.');
    }

    #[Endpoint(operationId: 'users.user.destroy', title: 'Delete record', description: 'Soft-delete or permanently remove a record.')]
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);
        /** @var User $actor */
        $actor = $request->user();
        $this->userService->delete($actor, $user);

        return $this->success(null, 'User deleted successfully.');
    }

    #[Endpoint(operationId: 'users.user.bulkDestroy', title: 'Bulk delete users', description: 'Soft-delete multiple users by ID.')]
    public function bulkDestroy(BulkDeleteUsersRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $count = $this->userService->deleteMany($actor, $request->validated('ids'));

        return $this->success(
            ['deleted' => $count],
            "{$count} user(s) deleted successfully."
        );
    }

    #[Endpoint(operationId: 'users.user.bulkSuspend', title: 'Bulk suspend users', description: 'Suspend multiple users by ID.')]
    public function bulkSuspend(BulkSuspendUsersRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $count = $this->userService->suspendMany($actor, $request->validated('ids'));

        return $this->success(
            ['suspended' => $count],
            "{$count} user(s) suspended successfully."
        );
    }

    #[Endpoint(operationId: 'users.user.bulkActivate', title: 'Bulk activate users', description: 'Activate multiple users by ID.')]
    public function bulkActivate(BulkActivateUsersRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $count = $this->userService->activateMany($actor, $request->validated('ids'));

        return $this->success(
            ['activated' => $count],
            "{$count} user(s) activated successfully."
        );
    }

    #[Endpoint(operationId: 'users.user.restore', title: 'Restore user', description: 'Restore a soft-deleted user.')]
    public function restore(User $user): JsonResponse
    {
        $this->authorize('restore', $user);
        $user = $this->userService->restore($user);

        return $this->success(new UserResource($user), 'User restored successfully.');
    }

    #[Endpoint(operationId: 'users.user.syncRoles', title: 'Sync roles', description: 'Replace assigned roles for the user.')]
    public function syncRoles(SyncUserRolesRequest $request, User $user): JsonResponse
    {
        $user = $this->userService->syncRoles($user, $request->validated('roles'));

        return $this->success(new UserResource($user), 'User roles synced successfully.');
    }

    #[Endpoint(operationId: 'users.user.syncPermissions', title: 'Sync permissions', description: 'Replace assigned permissions for the role or user.')]
    public function syncPermissions(SyncUserPermissionsRequest $request, User $user): JsonResponse
    {
        $user = $this->userService->syncPermissions($user, $request->validated('permissions'));

        return $this->success(new UserResource($user), 'User permissions synced successfully.');
    }

    #[Endpoint(operationId: 'users.user.updateStatus', title: 'Update status', description: 'Change lifecycle/status for the resource.')]
    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $status = $request->validated('status');
        $status = $status instanceof UserStatus ? $status : UserStatus::from($status);
        $user = $this->userService->updateStatus($actor, $user, $status);

        return $this->success(new UserResource($user), 'User status updated successfully.');
    }

    #[Endpoint(operationId: 'users.user.resetPassword', title: 'Reset password', description: 'Reset password using a valid reset token.')]
    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        $plain = $this->userService->resetPassword($user, $request->validated('password'));

        return $this->success([
            'temporary_password' => $plain,
        ], 'User password reset successfully.');
    }

    #[Endpoint(operationId: 'users.user.uploadAvatar', title: 'Upload avatar', description: 'Upload and attach a user avatar image.')]
    public function uploadAvatar(UploadUserAvatarRequest $request, User $user): JsonResponse
    {
        $user = $this->userService->uploadAvatar($user, $request->file('avatar'));

        return $this->success(new UserResource($user), 'Avatar uploaded successfully.');
    }

    #[Endpoint(operationId: 'users.user.security', title: 'Security summary', description: 'Return security-related profile details for a user.')]
    public function security(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return $this->success(
            $this->userService->securitySummary($user),
            'Security summary retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'users.user.activities', title: 'Activity history', description: 'Paginate activity log entries for this subject.')]
    public function activities(Request $request, User $user): JsonResponse
    {
        $this->authorize('viewActivity', $user);

        $activities = $this->userService->activities(
            $user,
            (int)$request->integer('per_page', 15)
        );

        return $this->paginated(
            ActivityResource::collection($activities),
            'User activities retrieved successfully.'
        );
    }
}

