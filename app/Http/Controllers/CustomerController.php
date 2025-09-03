<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Repositories\CustomerRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    private $customerRepository;

    public function __construct(CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public function index(Request $request)
    {
        try {
            // Check if user explicitly wants all customers (non-paginated)
            if ($request->has('all') && $request->get('all') === 'true') {
                return $this->getAllCustomers();
            }
            
            // Default to pagination
            [$perPage, $page] = $this->getPaginationParams($request);
            
            $paginatedCustomers = $this->customerRepository->getPaginatedCustomers($perPage, $page);
            
            return $this->paginatedResponse($paginatedCustomers, CustomerResource::class, 'Customers retrieved successfully');
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while retrieving the customers',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getAllCustomers()
    {
        try {
            $customers = $this->customerRepository->getAllCustomers();
            if ($customers->count() <= 0) {
                return response()->json(['message' => 'No customers found'], 404);
            }

            return CustomerResource::collection($customers);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while retrieving the customers',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function show ($id)
    {
        try {
            $customer = $this->customerRepository->getCustomerById($id);
            if (!$customer) {
                return response()->json(['message' => 'Customer not found'], 404);
            }

            return new CustomerResource($customer);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while retrieving the customer',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function store (CustomerRequest $customerRequest)
    {
        try {
            $customer = $this->customerRepository->createCustomer($customerRequest->validated());
            return (new CustomerResource($customer))
                ->additional(['message' => 'Customer created successfully'])
                ->response()
                ->setStatusCode(201);
        } catch (\Illuminate\Validation\ValidationException $th) {
            return response()->json(
                ['message' => 'Validation error', 'errors' => $th->errors()],
                422
            );
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while creating the customer',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function update (CustomerRequest $customerRequest, $customerId)
    {
        try {
            $customer = $this->customerRepository->getCustomerById($customerId);
            if (!$customer) {
                return response()->json(['message' => 'Customer not found'], 404);
            }
            $customer = $this->customerRepository->updateCustomer($customerId, $customerRequest->validated());

            return (new CustomerResource($customer))
                ->additional(['message' => 'Customer updated successfully'])
                ->response()
                ->setStatusCode(200);
        } catch (\Illuminate\Validation\ValidationException $th) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $th->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while updating the customer',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function destroy ($customerId)
    {
        try {
            $customer = $this->customerRepository->getCustomerById($customerId);
            if (!$customer) {
                return response()->json(['message' => 'Customer not found'], 404);
            }
            $this->customerRepository->deleteCustomer($customerId);
            return response()->json(['message' => 'Customer deleted successfully']);
        } catch (\Exception $e) {
            // Handle unexpected exceptions
            return response()->json([
                'message' => 'An error occurred while deleting the customer',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

}
