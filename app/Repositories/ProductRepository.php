<?php

namespace App\Repositories;

use App\Interfaces\ProductRepositoryInterface;
use App\Models\Product;
use App\Services\FileUploadService;
use App\Services\SlugCreatorService;
use Illuminate\Support\Facades\Log;

class ProductRepository implements ProductRepositoryInterface
{
    protected $model;
    protected $fileUploadService;
    protected $slugCreatorService;
    public function __construct(Product $product, FileUploadService $fileUploadService, SlugCreatorService $slugCreatorService)
    {
        $this->model = $product;
        $this->fileUploadService = $fileUploadService;
        $this->slugCreatorService = $slugCreatorService;
    }

    public function getAllProducts()
    {
        return $this->model->all();
    }

    public function getPaginatedProducts($perPage = 15, $page = 1)
    {
        return $this->model->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
    }

    public function getProductById($id)
    {
        return $this->model->find($id);
    }

    public function createProduct($data)
    {
        if (isset($data['image']) && asset($data['image'])) {
            $data['image'] = $this->fileUploadService->uploadFile($data['image'], 'products');
        }

        $data['slug'] = $this->slugCreatorService->createSlug($data['name']);

        return $this->model->create($data);
    }

    public function updateProduct($id, $data)
    {
        $product = $this->getProductById($id);
        if ($product) {
            if (isset($data['image']) && asset($data['image'])) {
                $this->fileUploadService->updateFile($data['image'], 'products', $product->image);
            }

            $data['slug'] = $this->slugCreatorService->createSlug($data['name']);

            $product->update($data);
            return $product;
        }
        return false;
    }

    public function deleteProduct($id)
    {
        $product = $this->getProductById($id);
        if ($product) {
            if ($product->image) {
                $this->fileUploadService->deleteFile($product->image, 'products');
            }

            $product->delete();
            return true;
        }
        return false;
    }
}
