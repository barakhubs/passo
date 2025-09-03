<?php

namespace App\Interfaces;

interface SaleRepositoryInterface
{
    public function getAllSales();
    public function getPaginatedSales($perPage = 15, $page = 1);
    public function getSaleById($id);
    public function createSale(array $SaleDetails);
    public function updateSale($id, array $SaleDetails);
    public function deleteSale($id);
}
