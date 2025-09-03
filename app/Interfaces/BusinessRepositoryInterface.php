<?php

namespace App\Interfaces;

interface BusinessRepositoryInterface
{
    public function getAllBusinesses();
    public function getPaginatedBusinesses($perPage = 15, $page = 1);
    public function getBusinessById($id);
    public function createBusiness(array $businessDetails);
    public function updateBusiness($id, array $businessDetails);
    public function deleteBusiness($id);
}
