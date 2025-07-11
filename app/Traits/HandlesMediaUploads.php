<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HandlesMediaUploads
{
    public function handleMediaUpload($model, $media, $collection = 'default')
    {
        if (!$media) {
            return;
        }
        if (is_array($media)) {
            foreach ($media as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $model->addMedia($file)->toMediaCollection($collection);
                }
            }
        } elseif ($media instanceof UploadedFile && $media->isValid()) {
            $model->addMedia($media)->toMediaCollection($collection);
        }
    }
    public function updateMedia($model, $media, $collection = 'default', $deleteOld = true)
    {
        if ($deleteOld) {
            $model->clearMediaCollection($collection);
        }
        $this->handleMediaUpload($model, $media, $collection);
    }

    /**
     * Upload a media file
     */
    protected function uploadMedia(UploadedFile $file, string $directory): string
    {
        // Generate file path
        $datePath = date('Y/m/d');
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $relativePath = "{$directory}/{$datePath}/{$filename}";
        
        // Create directory if it doesn't exist
        $fullDirectory = public_path(dirname($relativePath));
        if (!file_exists($fullDirectory)) {
            mkdir($fullDirectory, 0755, true);
        }
        
        // Move the file to the public directory
        $file->move($fullDirectory, $filename);
        
        return $relativePath;
    }

    /**
     * Delete a media file
     */
    protected function deleteMedia(?string $path): bool
    {
        if (!$path) {
            return false;
        }
        
        $fullPath = public_path($path);
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        
        return false;
    }
}