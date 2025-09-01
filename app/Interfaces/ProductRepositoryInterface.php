<?php

namespace App\Interfaces;

interface ProductRepositoryInterface
{
    public function getAllProducts();
    public function getPaginatedProducts($perPage = 15, $page = 1);
    public function getProductById($id);
    public function createProduct(array $productDetails);
    public function updateProduct($id, array $productDetails);
    public function deleteProduct($id);
}
