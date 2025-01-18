<?php

namespace App\Repositories;

use App\Interfaces\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Services\FileUploadService;
use App\Services\SlugCreatorService;
use Illuminate\Support\Facades\Log;

class CustomerRepository implements CustomerRepositoryInterface
{
    protected $model;
    protected $slugCreatorService;
    public function __construct(Customer $customer)
    {
        $this->model = $customer;
    }

    public function getAllCustomers()
    {
        return $this->model->all();
    }

    public function getCustomerById($id)
    {
        return $this->model->find($id);
    }

    public function createCustomer($data)
    {
        return $this->model->create($data);
    }

    public function updateCustomer($id, $data)
    {
        $customer = $this->getCustomerById($id);
        if ($customer) {
            $customer->update($data);
            return $customer;
        }
        return false;
    }

    public function deleteCustomer($id)
    {
        $customer = $this->getCustomerById($id);
        if ($customer) {
            $customer->delete();
            return true;
        }
        return false;
    }
}
