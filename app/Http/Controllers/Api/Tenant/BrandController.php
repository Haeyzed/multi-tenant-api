<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Brands\StoreBrandRequest;
use App\Http\Requests\Tenant\Brands\UpdateBrandRequest;
use App\Http\Resources\Tenant\BrandResource;
use App\Models\Tenant\Brand;
use App\Services\Tenant\BrandService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Tenant Brands', description: 'Product brand catalog for the current tenant.', weight: 20)]
final class BrandController extends Controller
{
    public function __construct(
        private readonly BrandService $brands,
    ) {}

    #[Endpoint(operationId: 'tenant.brands.index', title: 'List brands')]
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->brands->paginate($request->all());

        return $this->success(
            BrandResource::collection($paginator),
            'Brands retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'tenant.brands.statistics', title: 'Brand statistics')]
    public function statistics(): JsonResponse
    {
        return $this->success($this->brands->statistics(), 'Brand statistics retrieved successfully.');
    }

    #[Endpoint(operationId: 'tenant.brands.options', title: 'Brand options')]
    public function options(): JsonResponse
    {
        return $this->success($this->brands->options(), 'Brand options retrieved successfully.');
    }

    #[Endpoint(operationId: 'tenant.brands.store', title: 'Create brand')]
    public function store(StoreBrandRequest $request): JsonResponse
    {
        $brand = $this->brands->create($request->validated());

        return $this->success(new BrandResource($brand), 'Brand created successfully.', 201);
    }

    #[Endpoint(operationId: 'tenant.brands.show', title: 'Show brand')]
    public function show(Brand $brand): JsonResponse
    {
        return $this->success(new BrandResource($brand), 'Brand retrieved successfully.');
    }

    #[Endpoint(operationId: 'tenant.brands.showBySlug', title: 'Show brand by slug')]
    public function showBySlug(string $slug): JsonResponse
    {
        return $this->success(
            new BrandResource($this->brands->findBySlug($slug)),
            'Brand retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'tenant.brands.update', title: 'Update brand')]
    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $brand = $this->brands->update($brand, $request->validated());

        return $this->success(new BrandResource($brand), 'Brand updated successfully.');
    }

    #[Endpoint(operationId: 'tenant.brands.destroy', title: 'Delete brand')]
    public function destroy(Brand $brand): JsonResponse
    {
        $this->brands->delete($brand);

        return $this->success(null, 'Brand deleted successfully.');
    }

    #[Endpoint(operationId: 'tenant.brands.destroyMany', title: 'Bulk delete brands')]
    public function destroyMany(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ])['ids'];

        $deleted = $this->brands->deleteMany($ids);

        return $this->success(['deleted' => $deleted], 'Brands deleted successfully.');
    }

    #[Endpoint(operationId: 'tenant.brands.toggleVisibility', title: 'Toggle brand visibility')]
    public function toggleVisibility(Brand $brand): JsonResponse
    {
        return $this->success(
            new BrandResource($this->brands->toggleVisibility($brand)),
            'Brand visibility updated successfully.',
        );
    }

    #[Endpoint(operationId: 'tenant.brands.toggleFeatured', title: 'Toggle brand featured')]
    public function toggleFeatured(Brand $brand): JsonResponse
    {
        return $this->success(
            new BrandResource($this->brands->toggleFeatured($brand)),
            'Brand featured flag updated successfully.',
        );
    }

    #[Endpoint(operationId: 'tenant.brands.reorder', title: 'Reorder brands')]
    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ])['ids'];

        $this->brands->reorder($ids);

        return $this->success(null, 'Brands reordered successfully.');
    }
}
