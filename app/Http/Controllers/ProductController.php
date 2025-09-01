<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Repositories\ProductRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    private $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function index(Request $request)
    {
        try {
            // Check if user explicitly wants all products (non-paginated)
            if ($request->has('all') && $request->get('all') === 'true') {
                return $this->getAllProducts();
            }
            
            // Default to pagination
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);
            
            // Validate pagination parameters
            $perPage = min(max((int)$perPage, 1), 100); // Between 1 and 100
            $page = max((int)$page, 1); // At least 1
            
            $paginatedProducts = $this->productRepository->getPaginatedProducts($perPage, $page);
            
            if ($paginatedProducts->total() === 0) {
                return response()->json([
                    'message' => 'No products found',
                    'data' => [],
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                        'has_more_pages' => false,
                        'from' => null,
                        'to' => null
                    ]
                ], 200);
            }

            return response()->json([
                'message' => 'Products retrieved successfully',
                'data' => ProductResource::collection($paginatedProducts->items()),
                'pagination' => [
                    'current_page' => $paginatedProducts->currentPage(),
                    'per_page' => $paginatedProducts->perPage(),
                    'total' => $paginatedProducts->total(),
                    'last_page' => $paginatedProducts->lastPage(),
                    'has_more_pages' => $paginatedProducts->hasMorePages(),
                    'from' => $paginatedProducts->firstItem(),
                    'to' => $paginatedProducts->lastItem(),
                    'next_page_url' => $paginatedProducts->nextPageUrl(),
                    'prev_page_url' => $paginatedProducts->previousPageUrl()
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while retrieving the products',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getAllProducts()
    {
        try {
            $products = $this->productRepository->getAllProducts();
            if ($products->count() <= 0) {
                return response()->json(['message' => 'No products found'], 404);
            }

            return ProductResource::collection($products);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while retrieving the products',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function show ($id)
    {
        try {
            $product = $this->productRepository->getProductById($id);
            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            return new ProductResource($product);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while retrieving the product',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function store (ProductRequest $productRequest)
    {
        try {
            Log::info($productRequest->validated());
            $product = $this->productRepository->createProduct($productRequest->validated());
            return (new ProductResource($product))
                ->additional(['message' => 'Product created successfully'])
                ->response()
                ->setStatusCode(201);
        } catch (\Illuminate\Validation\ValidationException $th) {
            return response()->json(
                ['message' => 'Validation error', 'errors' => $th->errors()],
                422
            );
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while creating the product',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function update (ProductRequest $productRequest, $productId)
    {
        try {
            $product = $this->productRepository->getProductById($productId);
            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }
            $product = $this->productRepository->updateProduct($productId, $productRequest->validated());

            return (new ProductResource($product))
                ->additional(['message' => 'Product updated successfully'])
                ->response()
                ->setStatusCode(200);
        } catch (\Illuminate\Validation\ValidationException $th) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $th->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while updating the product',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function destroy ($productId)
    {
        try {
            $product = $this->productRepository->getProductById($productId);
            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }
            $this->productRepository->deleteProduct($productId);
            return response()->json(['message' => 'Product deleted successfully']);
        } catch (\Exception $e) {
            // Handle unexpected exceptions
            return response()->json([
                'message' => 'An error occurred while deleting the product',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

}
