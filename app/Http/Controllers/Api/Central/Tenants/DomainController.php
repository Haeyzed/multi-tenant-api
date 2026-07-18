<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Tenants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Domains\SetDomainRedirectRequest;
use App\Http\Requests\Central\Domains\StoreDomainRequest;
use App\Http\Requests\Central\Domains\UpdateDomainRequest;
use App\Http\Resources\Central\DomainResource;
use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use App\Services\Central\Tenants\DomainService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;

#[Group('Central Domains', description: 'Tenant domains, DNS, SSL, redirects.', weight: 100)]
final class DomainController extends Controller
{
    public function __construct(
        private readonly DomainService $domainService,
    )
    {
    }

    #[Endpoint(operationId: 'tenants.domain.index', title: 'List domains', description: 'Return a paginated list of domains.')]
    public function index(Tenant $tenant): JsonResponse
    {
        $this->authorize('viewAny', Domain::class);

        $domains = $tenant->domains()->latest()->get();

        return $this->success(DomainResource::collection($domains), 'Domains retrieved successfully.');
    }

    #[Endpoint(operationId: 'tenants.domain.store', title: 'Create domain', description: 'Create a new domain and return it.')]
    public function store(StoreDomainRequest $request, Tenant $tenant): JsonResponse
    {
        $domain = $this->domainService->create($tenant, $request->validated());

        return $this->success(new DomainResource($domain), 'Domain created successfully.', 201);
    }

    #[Endpoint(operationId: 'tenants.domain.show', title: 'Show record', description: 'Return a single record by ID.')]
    public function show(Tenant $tenant, Domain $domain): JsonResponse
    {
        $this->authorize('view', $domain);
        $this->ensureDomainBelongsToTenant($tenant, $domain);

        return $this->success(new DomainResource($domain), 'Domain retrieved successfully.');
    }

    private function ensureDomainBelongsToTenant(Tenant $tenant, Domain $domain): void
    {
        if ($domain->tenant_id !== $tenant->id) {
            abort(404);
        }
    }

    #[Endpoint(operationId: 'tenants.domain.update', title: 'Update domain', description: 'Update an existing domain and return it.')]
    public function update(UpdateDomainRequest $request, Tenant $tenant, Domain $domain): JsonResponse
    {
        $this->ensureDomainBelongsToTenant($tenant, $domain);
        $domain = $this->domainService->update($domain, $request->validated());

        return $this->success(new DomainResource($domain), 'Domain updated successfully.');
    }

    #[Endpoint(operationId: 'tenants.domain.destroy', title: 'Delete domain', description: 'Soft-delete or permanently remove a domain.')]
    public function destroy(Tenant $tenant, Domain $domain): JsonResponse
    {
        $this->authorize('delete', $domain);
        $this->ensureDomainBelongsToTenant($tenant, $domain);
        $this->domainService->delete($domain);

        return $this->success(null, 'Domain deleted successfully.');
    }

    #[Endpoint(operationId: 'tenants.domain.makePrimary', title: 'Make primary domain', description: 'Mark the domain as the tenant primary domain.')]
    public function makePrimary(Tenant $tenant, Domain $domain): JsonResponse
    {
        $this->authorize('managePrimary', $domain);
        $this->ensureDomainBelongsToTenant($tenant, $domain);
        $domain = $this->domainService->makePrimary($domain);

        return $this->success(new DomainResource($domain), 'Domain set as primary successfully.');
    }

    #[Endpoint(operationId: 'tenants.domain.regenerateDnsToken', title: 'Regenerate DNS token', description: 'Issue a new DNS verification token.')]
    public function regenerateDnsToken(Tenant $tenant, Domain $domain): JsonResponse
    {
        $this->authorize('verify', $domain);
        $this->ensureDomainBelongsToTenant($tenant, $domain);
        $domain = $this->domainService->regenerateDnsToken($domain);

        return $this->success(new DomainResource($domain), 'DNS verification token regenerated.');
    }

    #[Endpoint(operationId: 'tenants.domain.verifyDns', title: 'Verify DNS', description: 'Attempt DNS verification for the domain.')]
    public function verifyDns(Tenant $tenant, Domain $domain): JsonResponse
    {
        $this->authorize('verify', $domain);
        $this->ensureDomainBelongsToTenant($tenant, $domain);
        $domain = $this->domainService->verifyDns($domain);

        return $this->success(new DomainResource($domain), 'Domain DNS verified successfully.');
    }

    #[Endpoint(operationId: 'tenants.domain.enableSsl', title: 'Enable SSL', description: 'Enable SSL for the domain.')]
    public function enableSsl(Tenant $tenant, Domain $domain): JsonResponse
    {
        $this->authorize('manageSsl', $domain);
        $this->ensureDomainBelongsToTenant($tenant, $domain);
        $domain = $this->domainService->enableSsl($domain);

        return $this->success(new DomainResource($domain), 'SSL enabled successfully.');
    }

    #[Endpoint(operationId: 'tenants.domain.disableSsl', title: 'Disable SSL', description: 'Disable SSL for the domain.')]
    public function disableSsl(Tenant $tenant, Domain $domain): JsonResponse
    {
        $this->authorize('manageSsl', $domain);
        $this->ensureDomainBelongsToTenant($tenant, $domain);
        $domain = $this->domainService->disableSsl($domain);

        return $this->success(new DomainResource($domain), 'SSL disabled successfully.');
    }

    #[Endpoint(operationId: 'tenants.domain.setRedirect', title: 'Set redirect', description: 'Configure domain redirect rules.')]
    public function setRedirect(SetDomainRedirectRequest $request, Tenant $tenant, Domain $domain): JsonResponse
    {
        $this->ensureDomainBelongsToTenant($tenant, $domain);
        $domain = $this->domainService->setRedirect($domain, $request->validated('redirect_to'));

        return $this->success(new DomainResource($domain), 'Domain redirect updated successfully.');
    }
}

