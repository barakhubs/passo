<?php

namespace App\Services;

class FileUploadService
{
    public function uploadFile($file, $path)
    {
        $fileName = time() . '.' . $file->getClientOriginalExtension();
        $file->storeAs($path, $fileName);
        return $fileName;
    }

    public function deleteFile($fileName, $path)
    {
        $filePath = $path . '/' . $fileName;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    public function updateFile($file, $path, $fileName)
    {
        $this->deleteFile($fileName, $path);
        $this->uploadFile($file, $path);
    }
}
