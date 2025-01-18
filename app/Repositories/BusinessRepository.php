<?php

namespace App\Repositories;

use App\Interfaces\BusinessRepositoryInterface;
use App\Models\Business;
use App\Services\FileUploadService;
use App\Services\SlugCreatorService;

class BusinessRepository implements BusinessRepositoryInterface
{
    protected $model;
    protected $fileUploadService;
    protected $slugCreatorService;
    public function __construct(Business $business, FileUploadService $fileUploadService, SlugCreatorService $slugCreatorService)
    {
        $this->model = $business;
        $this->fileUploadService = $fileUploadService;
        $this->slugCreatorService = $slugCreatorService;
    }

    public function getAllBusinesses()
    {
        return $this->model->all();
    }

    public function getBusinessById($id)
    {
        return $this->model->find($id);
    }

    public function createBusiness($data)
    {
        if (isset($data['logo']) && asset($data['logo'])) {
            $data['logo'] = $this->fileUploadService->uploadFile($data['logo'], 'businesses');
        }

        $data['slug'] = $this->slugCreatorService->createSlug($data['name']);

        return $this->model->create($data);
    }

    public function updateBusiness($data, $id): mixed
    {
        $business = $this->getBusinessById($id);
        if ($business) {
            if (isset($data['logo']) && asset($data['logo'])) {
                $this->fileUploadService->updateFile($data['logo'], 'businesses', $business->logo);
            }

            $data['slug'] = $this->slugCreatorService->createSlug($data['name']);

            $business->update($data);

            return $business;
        }
        return false;
    }

    public function deleteBusiness($id)
    {
        $business = $this->getBusinessById($id);
        if ($business) {
            if ($business->logo) {
                $this->fileUploadService->deleteFile($business->logo, 'businesses');
            }

            $business->delete();
            return true;
        }
        return false;
    }
}
