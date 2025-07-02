<?php

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

function uploadImage(Request $request, $fieldName, $directory = 'images')
{
    if ($request->hasFile($fieldName)) {
        $image = $request->file($fieldName);
        $imageName = time() . '_' . $image->getClientOriginalName();
        $image->move(public_path($directory), $imageName);
        return $directory . '/' . $imageName;
    }
    return null;
}
function uploadVideo(Request $request, string $fieldName, string $directory = 'videos'): ?string
{
    if ($request->hasFile($fieldName)) {
        $video = $request->file($fieldName);
        $allowedExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];
        if (!in_array($video->getClientOriginalExtension(), $allowedExtensions)) {
            return null; 
        }
        $videoName = time() . '_' . uniqid() . '.' . $video->getClientOriginalExtension();
        $video->move(public_path($directory), $videoName);
        return $directory . '/' . $videoName;
    }

    return null;
}
function uploadImages(Request $request, $fieldName, $directory = 'images')
{
    $uploadedImages = [];

    if ($request->hasFile($fieldName)) {
        $images = $request->file($fieldName);

        foreach ($images as $image) {
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path($directory), $imageName);
            $uploadedImages[] = $directory . '/' . $imageName;
        }
    }

    return implode(',', $uploadedImages);
}
function updateImages(Request $request, $fieldName, $directory = 'images', $oldImages = [])
{
    $uploadedImages = [];

    if ($request->hasFile($fieldName)) {
        $images = $request->file($fieldName);

        foreach ($images as $image) {
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path($directory), $imageName);
            $uploadedImages[] = $directory . '/' . $imageName;
        }
    }

    foreach ($oldImages as $oldImage) {
        if (in_array($oldImage, $request->input('existing_images', []))) {
            $uploadedImages[] = $oldImage;
        } else {
            // Delete old image if not found in the new array
            if (file_exists(public_path($oldImage))) {
                unlink(public_path($oldImage));
            }
        }
    }

    return implode(',', $uploadedImages);
}
function deleteImage($imagePath)
{
    if ($imagePath && file_exists(public_path($imagePath))) {
        unlink(public_path($imagePath));
    }
}
function deleteImages($imagePaths)
{
    $paths = explode(',', $imagePaths);
    foreach ($paths as $imagePath) {
        if ($imagePath && file_exists(public_path($imagePath))) {
            unlink(public_path($imagePath));
        }
    }
}

function paginate($query, $resourceClass, $limit = 10, $pageNumber = 1)
{
    // $paginatedData = $query->paginate($limit);
    $paginatedData = $query->paginate($limit, ['*'], 'page', $pageNumber);
    return $resourceClass::collection($paginatedData)->response()->getData(true);
}

function paginateWithoutResource($query, $limit = 10, $pageNumber = 1)
{
    $paginatedData = $query->paginate($limit, ['*'], 'page', $pageNumber);

    return [
        'data' => $paginatedData->items(),
        'links' => [
            'first' => $paginatedData->url(1),
            'last' => $paginatedData->url($paginatedData->lastPage()),
            'prev' => $paginatedData->previousPageUrl(),
            'next' => $paginatedData->nextPageUrl(),
        ],
        'meta' => [
            'current_page' => $paginatedData->currentPage(),
            'from' => $paginatedData->firstItem(),
            'last_page' => $paginatedData->lastPage(),
            'path' => $paginatedData->path(),
            'per_page' => $paginatedData->perPage(),
            'to' => $paginatedData->lastItem(),
            'total' => $paginatedData->total(),
        ],
    ];
}

function generateUniqueSerialNumber($model)
{
    do {
        $serialNumber = strtoupper(Str::random(10));
    } while ($model::where('serial_number', $serialNumber)->exists());

    return $serialNumber;
}
