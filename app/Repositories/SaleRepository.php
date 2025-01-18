<?php

namespace App\Repositories;

use App\Interfaces\SaleRepositoryInterface;
use App\Models\Sale;

class SaleRepository implements SaleRepositoryInterface
{
    protected $model;

    public function __construct(Sale $sale)
    {
        $this->model = $sale;
    }

    public function getAllSales()
    {
        return $this->model->all();
    }

    public function getSaleById($id)
    {
        return $this->model->find($id);
    }

    public function createSale($data)
    {
        $data['total_amount'] = collect($data['items'])->sum('total');
        $sale = $this->model->create($data);
        $sale->saleItems()->createMany($data['items']);
        return $sale;
    }

    public function updateSale($id, $data)
    {
        $sale = $this->getSaleById($id);

        if (!$sale) {
            return false;
        }

        $sale->saleItems()->delete();
        $sale->saleItems()->createMany($data['items']);

        $sale->update($data);
        return $sale;
    }

    public function deleteSale($id)
    {
        $sale = $this->getSaleById($id);

        if (!$sale) {
            return false;
        }

        $sale->saleItems()->delete();
        return $sale->delete();
    }
}
