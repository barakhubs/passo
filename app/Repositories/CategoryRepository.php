<?php

namespace App\Repositories;

use App\Interfaces\CategoryRepositoryInterface;
use App\Models\Category;
use App\Services\FileUploadService;
use App\Services\SlugCreatorService;

class CategoryRepository implements CategoryRepositoryInterface
{
    protected $model;
    protected $fileUploadService;
    protected $slugCreatorService;
    public function __construct(Category $category, FileUploadService $fileUploadService, SlugCreatorService $slugCreatorService)
    {
        $this->model = $category;
        $this->fileUploadService = $fileUploadService;
        $this->slugCreatorService = $slugCreatorService;
    }

    public function getAllCategories()
    {
        return $this->model->all();
    }

    public function getCategoryById($id)
    {
        return $this->model->find($id);
    }

    public function createCategory($data)
    {
        if (isset($data['image']) && asset($data['image'])) {
            $data['image'] = $this->fileUploadService->uploadFile($data['image'], 'categories');
        }

        $data['slug'] = $this->slugCreatorService->createSlug($data['name']);

        return $this->model->create($data);
    }

    public function updateCategory($data, $id): mixed
    {
        $category = $this->getCategoryById($id);
        if ($category) {
            if (isset($data['image']) && asset($data['image'])) {
                $this->fileUploadService->updateFile($data['image'], 'categories', $category->image);
            }

            $data['slug'] = $this->slugCreatorService->createSlug($data['name']);

            $category->update($data);

            return $category;
        }
        return false;
    }

    public function deleteCategory($id)
    {
        $category = $this->getCategoryById($id);
        if ($category) {
            if ($category->image) {
                $this->fileUploadService->deleteFile($category->image, 'categories');
            }

            $category->delete();
            return true;
        }
        return false;
    }
}
