<?php

namespace App\Repositories;

use App\Interfaces\BusinessRepositoryInterface;
use App\Models\Business;
use App\Services\FileUploadService;
use App\Services\SlugCreatorService;
use Illuminate\Support\Facades\Auth;

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
        return $this->model->forCurrentUser()->get();
    }

    public function getBusinessById($id)
    {
        return $this->model->forCurrentUser()->find($id);
    }

    public function createBusiness($data)
    {
        $data['user_id'] = Auth::id();

        if (isset($data['logo']) && $data['logo']) {
            $data['logo'] = $this->fileUploadService->uploadFile($data['logo'], 'businesses');
        }

        $data['slug'] = $this->slugCreatorService->createSlug($data['name']);

        $business = $this->model->create($data);

        Auth::user()->update([
            'active_business_id' => $business->id
        ]);

        return $business;
    }

    public function updateBusiness($data, $id): mixed
    {
        $business = $this->model->forCurrentUser()->find($id);

        if ($business) {
            if (isset($data['logo']) && $data['logo']) {
                $data['logo'] = $this->fileUploadService->updateFile($data['logo'], 'businesses', $business->logo);
            }

            if (isset($data['name'])) {
                $data['slug'] = $this->slugCreatorService->createSlug($data['name']);
            }

            $business->update($data);
            return $business;
        }
        return false;
    }

    public function deleteBusiness($id)
    {
        $business = $this->model->forCurrentUser()->find($id);

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
