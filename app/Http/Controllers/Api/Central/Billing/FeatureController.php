<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Features\StoreFeatureCategoryRequest;
use App\Http\Requests\Central\Features\StoreFeatureRequest;
use App\Http\Requests\Central\Features\UpdateFeatureCategoryRequest;
use App\Http\Requests\Central\Features\UpdateFeatureRequest;
use App\Http\Resources\Central\FeatureCategoryResource;
use App\Http\Resources\Central\FeatureResource;
use App\Models\Central\Feature;
use App\Models\Central\FeatureCategory;
use App\Services\Central\Billing\FeatureService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Features', description: 'Feature catalog and categories.', weight: 110)]
final class FeatureController extends Controller
{
    public function __construct(
        private readonly FeatureService $featureService,
    )
    {
    }

    #[Endpoint(operationId: 'billing.feature.index', title: 'List features', description: 'Return a paginated list of features.')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Feature::class);

        $features = $this->featureService->paginate($request->only([
            'search', 'status', 'category_id', 'available', 'per_page',
        ]));

        return $this->paginated(FeatureResource::collection($features), 'Features retrieved successfully.');
    }

    #[Endpoint(operationId: 'billing.feature.store', title: 'Create feature', description: 'Create a new feature and return it.')]
    public function store(StoreFeatureRequest $request): JsonResponse
    {
        $feature = $this->featureService->create($request->validated());

        return $this->success(new FeatureResource($feature), 'Feature created successfully.', 201);
    }

    #[Endpoint(operationId: 'billing.feature.show', title: 'Show record', description: 'Return a single record by ID.')]
    public function show(Feature $feature): JsonResponse
    {
        $this->authorize('view', $feature);
        $feature->load('category');

        return $this->success(new FeatureResource($feature), 'Feature retrieved successfully.');
    }

    #[Endpoint(operationId: 'billing.feature.update', title: 'Update feature', description: 'Update an existing feature and return it.')]
    public function update(UpdateFeatureRequest $request, Feature $feature): JsonResponse
    {
        $feature = $this->featureService->update($feature, $request->validated());

        return $this->success(new FeatureResource($feature), 'Feature updated successfully.');
    }

    #[Endpoint(operationId: 'billing.feature.destroy', title: 'Delete record', description: 'Soft-delete or permanently remove a record.')]
    public function destroy(Feature $feature): JsonResponse
    {
        $this->authorize('delete', $feature);
        $this->featureService->delete($feature);

        return $this->success(null, 'Feature deleted successfully.');
    }

    #[Endpoint(operationId: 'billing.feature.restore', title: 'Restore feature', description: 'Restore a soft-deleted feature.')]
    public function restore(Feature $feature): JsonResponse
    {
        $this->authorize('restore', $feature);
        $feature = $this->featureService->restore($feature);

        return $this->success(new FeatureResource($feature), 'Feature restored successfully.');
    }

    #[Endpoint(operationId: 'billing.feature.categories', title: 'List categories', description: 'List categories for this resource.')]
    public function categories(): JsonResponse
    {
        $this->authorize('viewAny', Feature::class);

        $categories = $this->featureService->listCategories();

        return $this->success(
            FeatureCategoryResource::collection($categories),
            'Feature categories retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.feature.categoryOptions', title: 'Category options', description: 'Return feature category dropdown options as value/label pairs.')]
    public function categoryOptions(): JsonResponse
    {
        $this->authorize('viewAny', Feature::class);

        return $this->success(
            $this->featureService->categoryOptionsForDropdown(),
            'Feature category options retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.feature.storeCategory', title: 'Create category', description: 'Create a new category.')]
    public function storeCategory(StoreFeatureCategoryRequest $request): JsonResponse
    {
        $category = $this->featureService->createCategory($request->validated());

        return $this->success(new FeatureCategoryResource($category), 'Feature category created successfully.', 201);
    }

    #[Endpoint(operationId: 'billing.feature.updateCategory', title: 'Update category', description: 'Update an existing category.')]
    public function updateCategory(UpdateFeatureCategoryRequest $request, FeatureCategory $featureCategory): JsonResponse
    {
        $category = $this->featureService->updateCategory($featureCategory, $request->validated());

        return $this->success(new FeatureCategoryResource($category), 'Feature category updated successfully.');
    }

    #[Endpoint(operationId: 'billing.feature.destroyCategory', title: 'Delete category', description: 'Delete a category.')]
    public function destroyCategory(FeatureCategory $featureCategory): JsonResponse
    {
        $this->authorize('manageCategories', Feature::class);
        $this->featureService->deleteCategory($featureCategory);

        return $this->success(null, 'Feature category deleted successfully.');
    }
}

