<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Interfaces\CategoryRepositoryInterface;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    private $categoryRepository;

    public function __construct(CategoryRepositoryInterface $categoryRepositoryInterface)
    {
        $this->categoryRepository = $categoryRepositoryInterface;
    }

    public function index(Request $request)
    {
        try {
            // Check if user explicitly wants all categories (non-paginated)
            if ($request->has('all') && $request->get('all') === 'true') {
                return $this->getAllCategories();
            }
            
            // Default to pagination
            [$perPage, $page] = $this->getPaginationParams($request);
            
            $paginatedCategories = $this->categoryRepository->getPaginatedCategories($perPage, $page);
            
            return $this->paginatedResponse($paginatedCategories, CategoryResource::class, 'Categories retrieved successfully');
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while retrieving the categories',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getAllCategories()
    {
        try {
            $categories = $this->categoryRepository->getAllCategories();
            if ($categories->count() <= 0) {
                return response()->json(['message' => 'No categories found'], 404);
            }

            return CategoryResource::collection($categories);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while retrieving the categories',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function show ($categoryId)
    {
        try {
            $category = $this->categoryRepository->getCategoryById($categoryId);
            if (!$category) {
                return response()->json(['message' => 'Category not found'], 404);
            }
            return new CategoryResource($category);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while retrieving the category',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function store (CategoryRequest $categoryRequest)
    {
        try {
            $category = $this->categoryRepository->createCategory($categoryRequest->validated());
            return (new CategoryResource($category))
                    ->additional(['message' => 'Category created successfully'])
                    ->response()
                    ->setStatusCode(201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while creating the category',
                'error' => $th->getMessage(),
            ], 500);
        }

    }

    public function update (CategoryRequest $categoryRequest, $categoryId)
    {
        try {
            $category = $this->categoryRepository->getCategoryById($categoryId);
            if (!$category) {
                return response()->json(['message' => 'Category not found'], 404);
            }
            $category = $this->categoryRepository->updateCategory($categoryRequest->validated(), $categoryId);
            return (new CategoryResource($category))
                ->additional(['message' => 'Category updated successfully'])
                ->response()
                ->setStatusCode(201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while updating the category',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function destroy ($categoryId)
    {
        try {
            $category = $this->categoryRepository->getCategoryById($categoryId);
            if (!$category) {
                return response()->json(['message' => 'Category not found'], 404);
            }
            $this->categoryRepository->deleteCategory($categoryId);
            return response()->json(['message' => 'Category deleted successfully']);
        } catch (\Exception $e) {
            // Handle unexpected exceptions
            return response()->json([
                'message' => 'An error occurred while deleting the category',
                'error' => $e->getMessage(),
            ], 500);
        }

    }
}
