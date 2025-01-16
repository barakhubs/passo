<?php

namespace App\Repositories;

use App\Interfaces\CategoryRepositoryInterface;
use App\Models\Category;

class CategoryRepository implements CategoryRepositoryInterface
{
    protected $model;
    public function __construct(Category $category)
    {
        $this->model = $category;
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
        return $this->model->create($data);
    }

    public function updateCategory($id, $data): mixed
    {
        $category = $this->getCategoryById($id);
        if ($category) {
            $category->update($data);
            return $category;
        }
        return false;
    }

    public function deleteCategory($id)
    {
        $category = $this->getCategoryById($id);
        if ($category) {
            $category->delete();
            return true;
        }
        return false;
    }
}
