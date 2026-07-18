<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Settings\BulkUpdateSettingsRequest;
use App\Http\Requests\Central\Settings\StoreSettingRequest;
use App\Http\Requests\Central\Settings\UpdateSettingRequest;
use App\Http\Resources\Central\SettingResource;
use App\Mail\Central\SettingsTestMail;
use App\Models\Central\Setting;
use App\Services\Central\Settings\ApplySettingsToConfig;
use App\Services\Central\Settings\SettingService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Group('Central Settings', description: 'Global platform settings.', weight: 160)]
final class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
        private readonly ApplySettingsToConfig $applySettingsToConfig,
    ) {
    }

    #[Endpoint(operationId: 'settings.setting.index', title: 'List settings', description: 'Return a paginated list of settings.')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        $settings = $this->settingService->list($request->only(['group', 'search', 'public']));

        return $this->success(
            SettingResource::collection($settings),
            'Settings retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'settings.setting.groups', title: 'Setting groups', description: 'List available setting groups.')]
    public function groups(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        return $this->success(
            $this->settingService->groups(),
            'Setting groups retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'settings.setting.grouped', title: 'Grouped settings', description: 'Return settings keyed by group.')]
    public function grouped(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        $grouped = collect($this->settingService->grouped($request->only(['group', 'search', 'public'])))
            ->map(fn($items) => SettingResource::collection($items)->resolve());

        return $this->success($grouped, 'Grouped settings retrieved successfully.');
    }

    #[Endpoint(operationId: 'settings.setting.public', title: 'Public settings', description: 'Return public settings as a key/value map, optionally filtered by group.')]
    public function publicSettings(Request $request): JsonResponse
    {
        return $this->success(
            $this->settingService->publicMap($request->string('group')->toString() ?: null),
            'Public settings retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'settings.setting.store', title: 'Create setting', description: 'Create a new setting and return it.')]
    public function store(StoreSettingRequest $request): JsonResponse
    {
        $setting = $this->settingService->create($request->validated());

        return $this->success(new SettingResource($setting), 'Setting created successfully.', 201);
    }

    #[Endpoint(operationId: 'settings.setting.show', title: 'Show record', description: 'Return a single record by ID.')]
    public function show(Setting $setting): JsonResponse
    {
        $this->authorize('view', $setting);

        return $this->success(new SettingResource($setting), 'Setting retrieved successfully.');
    }

    #[Endpoint(operationId: 'settings.setting.update', title: 'Update setting', description: 'Update an existing setting and return it.')]
    public function update(UpdateSettingRequest $request, Setting $setting): JsonResponse
    {
        $setting = $this->settingService->update($setting, $request->validated());

        return $this->success(new SettingResource($setting), 'Setting updated successfully.');
    }

    #[Endpoint(operationId: 'settings.setting.bulkUpdate', title: 'Bulk update settings', description: 'Update multiple settings by key in one request.')]
    public function bulkUpdate(BulkUpdateSettingsRequest $request): JsonResponse
    {
        $settings = $this->settingService->bulkUpdate($request->validated('settings'));

        return $this->success(
            SettingResource::collection($settings),
            'Settings updated successfully.',
        );
    }

    #[Endpoint(operationId: 'settings.mail.test', title: 'Send test email', description: 'Send a test email using the current mail settings.')]
    public function sendTestMail(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('settings.update'), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        ($this->applySettingsToConfig)();

        try {
            Mail::to($data['email'])->send(new SettingsTestMail);
        } catch (Throwable $exception) {
            return $this->error(
                'Failed to send test email: '.$exception->getMessage(),
                422
            );
        }

        return $this->success([
            'email' => $data['email'],
            'mailer' => config('mail.default'),
        ], 'Test email sent successfully.');
    }

    #[Endpoint(operationId: 'settings.setting.destroy', title: 'Delete setting', description: 'Soft-delete or permanently remove a setting.')]
    public function destroy(Setting $setting): JsonResponse
    {
        $this->authorize('delete', $setting);
        $this->settingService->delete($setting);

        return $this->success(null, 'Setting deleted successfully.');
    }
}

