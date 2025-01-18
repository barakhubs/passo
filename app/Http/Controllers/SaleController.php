<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleRequest;
use App\Http\Resources\SaleResource;
use App\Repositories\SaleRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SaleController extends Controller
{
    private $saleRepository;

    public function __construct(SaleRepository $saleRepository)
    {
        $this->saleRepository = $saleRepository;
    }

    public function index ()
    {
        try {
            $sales = $this->saleRepository->getAllSales();
            if ($sales->count() <= 0) {
                return response()->json(['message' => 'No sales found'], 404);
            }

            return SaleResource::collection($sales);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while retrieving the sales',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function show ($id)
    {
        try {
            $sale = $this->saleRepository->getSaleById($id);
            if (!$sale) {
                return response()->json(['message' => 'Sale not found'], 404);
            }

            return new SaleResource($sale);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while retrieving the sale',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function store (SaleRequest $saleRequest)
    {
        try {
            $sale = $this->saleRepository->createSale($saleRequest->validated());
            return (new SaleResource($sale))
                ->additional(['message' => 'Sale created successfully'])
                ->response()
                ->setStatusCode(201);
        } catch (\Illuminate\Validation\ValidationException $th) {
            return response()->json(
                ['message' => 'Validation error', 'errors' => $th->errors()],
                422
            );
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while creating the sale',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function update (SaleRequest $saleRequest, $saleId)
    {
        try {
            $sale = $this->saleRepository->getSaleById($saleId);
            if (!$sale) {
                return response()->json(['message' => 'Sale not found'], 404);
            }
            $sale = $this->saleRepository->updateSale($saleId, $saleRequest->validated());

            return (new SaleResource($sale))
                ->additional(['message' => 'Sale updated successfully'])
                ->response()
                ->setStatusCode(200);
        } catch (\Illuminate\Validation\ValidationException $th) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $th->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while updating the sale',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function destroy ($saleId)
    {
        try {
            $sale = $this->saleRepository->getSaleById($saleId);
            if (!$sale) {
                return response()->json(['message' => 'Sale not found'], 404);
            }
            $this->saleRepository->deleteSale($saleId);
            return response()->json(['message' => 'Sale deleted successfully']);
        } catch (\Exception $e) {
            // Handle unexpected exceptions
            return response()->json([
                'message' => 'An error occurred while deleting the sale',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

}
