<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;

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
    public function deleteMedia($model, $collection = 'default')
    {
        $model->clearMediaCollection($collection);
        return true;
    }
}