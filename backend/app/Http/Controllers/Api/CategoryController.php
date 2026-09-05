<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Category::where('user_id', $request->user()->id);

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->value());
        }

        if ($request->boolean('root_only')) {
            $query->whereNull('parent_id');
        }

        if (!$request->boolean('include_inactive')) {
            $query->active();
        }

        $query->with('children');

        $categories = $query->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return CategoryResource::collection($categories);
    }

    public function tree(Request $request): \Illuminate\Http\JsonResponse
    {
        $type = $request->string('type')->value();

        $roots = Category::where('user_id', $request->user()->id)
            ->whereNull('parent_id')
            ->active()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return response()->json($roots->toArray());
    }

    public function store(StoreCategoryRequest $request): CategoryResource
    {
        $category = Category::create([
            'user_id' => $request->user()->id,
            'name' => $request->string('name'),
            'type' => $request->string('type'),
            'parent_id' => $request->integer('parent_id') ?: null,
            'icon' => $request->input('icon'),
            'color' => $request->input('color'),
            'sort_order' => (int) $request->integer('sort_order', 0),
            'description' => $request->input('description'),
            'level' => $this->resolveLevel($request->integer('parent_id') ?: null),
        ]);

        return new CategoryResource($category);
    }

    public function show(Request $request, Category $category): CategoryResource
    {
        $this->authorize($request, $category);
        $category->load('children');
        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $this->authorize($request, $category);

        if ($category->is_system) {
            abort(403, 'System categories cannot be modified.');
        }

        $category->update($request->only([
            'name', 'icon', 'color', 'sort_order', 'is_active', 'description',
        ]));

        return new CategoryResource($category->fresh());
    }

    public function destroy(Request $request, Category $category)
    {
        $this->authorize($request, $category);

        if ($category->is_system) {
            abort(403, 'System categories cannot be deleted.');
        }

        if ($category->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category: has child categories',
            ], 400);
        }

        if ($category->transactions()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category: has existing transactions',
            ], 400);
        }

        $category->delete();
        return response()->noContent();
    }

    private function resolveLevel(?int $parentId): int
    {
        if (!$parentId) {
            return 0;
        }

        $parent = Category::find($parentId);
        return $parent ? $parent->level + 1 : 0;
    }

    private function authorize(Request $request, Category $category): void
    {
        if ($category->user_id !== $request->user()->id) {
            abort(404, 'Category not found.');
        }
    }
}
