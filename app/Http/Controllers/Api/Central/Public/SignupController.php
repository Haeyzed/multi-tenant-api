<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Public\CompletePublicSignupRequest;
use App\Http\Requests\Central\Public\SignupPaymentOptionsRequest;
use App\Http\Requests\Central\Public\StorePublicSignupRequest;
use App\Http\Resources\Central\PlanResource;
use App\Http\Resources\Central\PublicSignupResource;
use App\Services\Central\Billing\CardVerificationService;
use App\Services\Central\Billing\PlanService;
use App\Services\Central\Tenants\SignupIntentService;
use App\Services\Central\Tenants\TenantSignupService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Public self-serve signup and plan catalog endpoints.
 */
#[Group('Central Public Signup', description: 'Unauthenticated plan catalog and trial signup.', weight: 15)]
final class SignupController extends Controller
{
    public function __construct(
        private readonly PlanService $planService,
        private readonly TenantSignupService $tenantSignupService,
        private readonly SignupIntentService $signupIntentService,
        private readonly CardVerificationService $cardVerification,
    ) {}

    /**
     * List publicly visible plans for self-serve signup.
     */
    #[Endpoint(
        operationId: 'public.plans.index',
        title: 'List public plans',
        description: 'Return paginated plans that are active and publicly visible for self-serve signup.',
    )]
    public function plans(Request $request): JsonResponse
    {
        $plans = $this->planService->paginatePublic($request->only([
            'per_page',
            'country',
            'currency',
            'interval',
        ]));

        return $this->paginated(PlanResource::collection($plans), 'Public plans retrieved successfully.');
    }

    /**
     * List public plans as value/label pairs for dropdowns.
     */
    #[Endpoint(
        operationId: 'public.plans.options',
        title: 'Public plan options',
        description: 'Return active public plans as dropdown options with value and label (localized by country).',
    )]
    public function planOptions(Request $request): JsonResponse
    {
        return $this->success(
            $this->planService->optionsForDropdown($request->only(['country', 'currency', 'interval'])),
            'Public plan options retrieved successfully.',
        );
    }

    /**
     * List payment providers available for signup card verification by country.
     */
    #[Endpoint(
        operationId: 'public.signup.paymentOptions',
        title: 'Signup payment options',
        description: 'Resolve currency from country and return configured gateways that support it.',
    )]
    public function paymentOptions(SignupPaymentOptionsRequest $request): JsonResponse
    {
        $country = strtoupper(trim((string) $request->validated('country')));

        $result = $this->signupIntentService->paymentOptions($country);

        return $this->success($result, 'Signup payment options retrieved successfully.');
    }

    /**
     * Start card verification for self-serve signup (no tenant created yet).
     */
    #[Endpoint(
        operationId: 'public.signup.setup',
        title: 'Start signup card verification',
        description: 'Validate signup data and return a hosted checkout URL for soft card verification.',
    )]
    public function setup(StorePublicSignupRequest $request): JsonResponse
    {
        $result = $this->signupIntentService->setup($request->validated());

        return $this->success($result, 'Continue to the payment provider to verify your card.', 201);
    }

    /**
     * Complete signup after successful card verification.
     */
    #[Endpoint(
        operationId: 'public.signup.complete',
        title: 'Complete signup after card verification',
        description: 'Confirm the provider setup session and create the tenant + trial subscription.',
    )]
    public function complete(CompletePublicSignupRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->signupIntentService->complete(
            (string) $data['signup_intent_id'],
            $data,
        );

        return $this->success(
            new PublicSignupResource($result),
            'Tenant created successfully. Your trial has started.',
            201,
        );
    }

    /**
     * Create a tenant without card verification when the setting is disabled.
     */
    #[Endpoint(
        operationId: 'public.signup.store',
        title: 'Self-serve trial signup',
        description: 'Create a tenant on a public plan. When card verification is enabled, use /public/signup/setup instead.',
    )]
    public function store(StorePublicSignupRequest $request): JsonResponse
    {
        if ($this->cardVerification->isRequired()) {
            throw ValidationException::withMessages([
                'payment' => ['Card verification is required. Use the signup setup endpoint first.'],
            ]);
        }

        $result = $this->tenantSignupService->signup($request->validated());

        return $this->success(
            new PublicSignupResource($result),
            'Tenant created successfully. Your trial has started.',
            201,
        );
    }
}
