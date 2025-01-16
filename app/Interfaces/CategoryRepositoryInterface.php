<?php

namespace App\Interfaces;

interface CategoryRepositoryInterface
{
    public function getAllCategories();
    public function getCategoryById($id);
    public function createCategory(array $categoryDetails);
    public function updateCategory($id, array $categoryDetails);
    public function deleteCategory($id);
}
