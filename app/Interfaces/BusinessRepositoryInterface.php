<?php

namespace App\Interfaces;

interface BusinessRepositoryInterface
{
    public function getAllBusinesses();
    public function getBusinessById($id);
    public function createBusiness(array $businessDetails);
    public function updateBusiness($id, array $businessDetails);
    public function deleteBusiness($id);
}
