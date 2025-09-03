<?php

namespace App\Http\Controllers;

use App\Http\Requests\BusinessRequest;
use App\Http\Resources\BusinessResource;
use App\Interfaces\BusinessRepositoryInterface;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    private $businessRepository;

    public function __construct(BusinessRepositoryInterface $businessRepositoryInterface)
    {
        $this->businessRepository = $businessRepositoryInterface;
    }

    public function index(Request $request)
    {
        try {
            // Check if user explicitly wants all businesses (non-paginated)
            if ($request->has('all') && $request->get('all') === 'true') {
                return $this->getAllBusinesses();
            }

            // Default to pagination
            [$perPage, $page] = $this->getPaginationParams($request);

            $paginatedBusinesses = $this->businessRepository->getPaginatedBusinesses($perPage, $page);

            return $this->paginatedResponse($paginatedBusinesses, BusinessResource::class, 'Businesses retrieved successfully');
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while retrieving the businesses',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getAllBusinesses()
    {
        try {
            $businesses = $this->businessRepository->getAllBusinesses();
            if ($businesses->count() <= 0) {
                return response()->json(['message' => 'No businesses found'], 404);
            }

            return BusinessResource::collection($businesses);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while retrieving the businesses',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function show($businessId)
    {
        try {
            $business = $this->businessRepository->getBusinessById($businessId);
            if (!$business) {
                return response()->json(['message' => 'Business not found'], 404);
            }
            return new BusinessResource($business);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while retrieving the business',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function store(BusinessRequest $businessRequest)
    {
        try {
            $business = $this->businessRepository->createBusiness($businessRequest->validated());
            return (new BusinessResource($business))
                ->additional(['message' => 'Business created successfully'])
                ->response()
                ->setStatusCode(201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while creating the business',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function update(BusinessRequest $businessRequest, $businessId)
    {
        try {
            $business = $this->businessRepository->getBusinessById($businessId);
            if (!$business) {
                return response()->json(['message' => 'Business not found'], 404);
            }
            $business = $this->businessRepository->updateBusiness($businessRequest->validated(), $businessId);
            return (new BusinessResource($business))
                ->additional(['message' => 'Business updated successfully'])
                ->response()
                ->setStatusCode(201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while updating the business',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function destroy($businessId)
    {
        try {
            $business = $this->businessRepository->getBusinessById($businessId);
            if (!$business) {
                return response()->json(['message' => 'Business not found'], 404);
            }
            $this->businessRepository->deleteBusiness($businessId);
            return response()->json(['message' => 'Business deleted successfully']);
        } catch (\Exception $e) {
            // Handle unexpected exceptions
            return response()->json([
                'message' => 'An error occurred while deleting the business',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
