<?php

namespace App\Interfaces;

interface CustomerRepositoryInterface
{
    public function getAllCustomers();
    public function getCustomerById($id);
    public function createCustomer(array $CustomerDetails);
    public function updateCustomer($id, array $CustomerDetails);
    public function deleteCustomer($id);
}
