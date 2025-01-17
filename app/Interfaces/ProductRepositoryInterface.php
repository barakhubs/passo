<?php

namespace App\Interfaces;

interface ProductRepositoryInterface
{
    public function getAllProducts();
    public function getProductById($id);
    public function createProduct(array $productDetails);
    public function updateProduct($id, array $productDetails);
    public function deleteProduct($id);
}
