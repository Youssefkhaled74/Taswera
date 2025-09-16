<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhotoResource;
use App\Models\Photo;
use App\Models\User;
use App\Models\PhotoSelection;
use App\Models\PhotoSelected;
use App\Http\Resources\PhotoSelectedResource;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PhotoLookupController extends Controller
{
    use ApiResponse;

    public function getPhotosByBarcodePrefix(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barcode_prefix' => 'required|string|min:4',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }

        $barcodePrefix = $request->query('barcode_prefix');

        $photos = Photo::with(['staff', 'branch'])
            ->where('barcode_prefix', $barcodePrefix)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse([
            'photos' => PhotoResource::collection($photos)->resolve(),
        ], 'Photos retrieved successfully');
    }
    public function selectAndClonePhotos(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string|min:4',
            'phone_number' => 'required|string',
            'photo_ids' => 'required|array|min:1',
            'photo_ids.*.id' => 'required|integer|exists:photos,id',
            'photo_ids.*.large_quantity' => 'required|integer|min:0',
            'photo_ids.*.small_quantity' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }

        $data = $validator->validated();

        try {
            $user = User::where('barcode', 'LIKE', $data['barcode'] . '%')
                ->where('phone_number', $data['phone_number'])
                ->first();

            if (!$user) {
                return $this->errorResponse('User not found', Response::HTTP_NOT_FOUND);
            }

            $barcodePrefix = $data['barcode'];

            return DB::transaction(function () use ($user, $barcodePrefix, $data) {
                $photoItems = collect($data['photo_ids']);
                $photoIds = $photoItems->pluck('id')->all();

                $originalPhotos = Photo::whereIn('id', $photoIds)
                    ->where('barcode_prefix', $barcodePrefix)
                    ->get()
                    ->keyBy('id');

                if ($originalPhotos->count() !== count($photoIds)) {
                    return $this->errorResponse('Invalid photo selection', Response::HTTP_BAD_REQUEST);
                }

                // Replace: remove previous selected-photo rows for this barcode
                PhotoSelected::where('user_id', $user->id)
                    ->where('barcode_prefix', $barcodePrefix)
                    ->delete();

                $createdSelectedRows = [];

                foreach ($photoItems as $item) {
                    $photoId = (int) $item['id'];
                    $largeQuantity = (int) $item['large_quantity'];
                    $smallQuantity = (int) $item['small_quantity'];
                    $original = $originalPhotos[$photoId];

                    // Process large photos
                    for ($i = 0; $i < $largeQuantity; $i++) {
                        $newFilePath = $this->clonePhotoFile($original);
                        $createdSelectedRows[] = PhotoSelected::create([
                            'user_id' => $user->id,
                            'original_photo_id' => $photoId,
                            'quantity' => 1,
                            'type' => 'large',
                            'barcode_prefix' => $barcodePrefix,
                            'file_path' => $newFilePath,
                            'original_filename' => $original->original_filename,
                            'uploaded_by' => $original->uploaded_by,
                            'branch_id' => $original->branch_id,
                            'is_edited' => (bool) ($original->is_edited ?? false),
                            'thumbnail_path' => $original->thumbnail_path,
                            'status' => 'pending',
                            'sync_status' => 'pending',
                            'metadata' => array_merge($original->metadata ?? [], [
                                'cloned_from' => $original->id,
                                'created_from' => 'photo_selected',
                                'duplicate_index' => $i + 1,
                            ]),
                        ]);
                    }

                    // Process small photos
                    for ($i = 0; $i < $smallQuantity; $i++) {
                        $newFilePath = $this->clonePhotoFile($original);
                        $createdSelectedRows[] = PhotoSelected::create([
                            'user_id' => $user->id,
                            'original_photo_id' => $photoId,
                            'quantity' => 1,
                            'type' => 'small',
                            'barcode_prefix' => $barcodePrefix,
                            'file_path' => $newFilePath,
                            'original_filename' => $original->original_filename,
                            'uploaded_by' => $original->uploaded_by,
                            'branch_id' => $original->branch_id,
                            'is_edited' => (bool) ($original->is_edited ?? false),
                            'thumbnail_path' => $original->thumbnail_path,
                            'status' => 'pending',
                            'sync_status' => 'pending',
                            'metadata' => array_merge($original->metadata ?? [], [
                                'cloned_from' => $original->id,
                                'created_from' => 'photo_selected',
                                'duplicate_index' => $i + 1,
                            ]),
                        ]);
                    }
                }

                return $this->successResponse([
                    'created_count' => count($createdSelectedRows),
                    'photo_selected' => PhotoSelectedResource::collection(collect($createdSelectedRows))->resolve(),
                ], 'Photo selected records created successfully');
            });
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Clone the photo file and return the new file path.
     *
     * @param \App\Models\Photo $original
     * @return string
     */
    private function clonePhotoFile($original): string
    {
        $filePath = $original->file_path;
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $filenameOnly = pathinfo($filePath, PATHINFO_FILENAME);
        $dirOnly = rtrim(str_replace('/' . basename($filePath), '', $filePath), '/');
        $newFilename = $filenameOnly . '_' . uniqid('dup_') . ($extension ? ".{$extension}" : '');

        if (Str::startsWith($filePath, '/storage/')) {
            $srcRel = ltrim(Str::after($filePath, '/storage/'), '/');
            $destRel = trim(dirname($srcRel), '/') . '/' . $newFilename;
            Storage::disk('public')->makeDirectory(trim(dirname($srcRel), '/'));
            Storage::disk('public')->copy($srcRel, $destRel);
            return '/storage/' . $destRel;
        }

        $srcAbs = public_path(ltrim($filePath, '/'));
        $destDirAbs = dirname($srcAbs);
        File::ensureDirectoryExists($destDirAbs);
        $destAbs = $destDirAbs . DIRECTORY_SEPARATOR . $newFilename;
        if (File::exists($srcAbs)) {
            File::copy($srcAbs, $destAbs);
        }
        $rel = str_replace(public_path(), '', $destAbs);
        return '/' . str_replace('\\', '/', ltrim($rel, DIRECTORY_SEPARATOR));
    }

    public function updateSelectedPhoto(Request $request, PhotoSelected $selected): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }

        try {
            $file = $request->file('photo');
            $originalName = $file->getClientOriginalName();

            $filePath = $selected->file_path;
            if (!$filePath) {
                return $this->errorResponse('Selected photo has no file path to replace', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (Str::startsWith($filePath, '/storage/')) {
                $rel = ltrim(Str::after($filePath, '/storage/'), '/');
                $dir = trim(dirname($rel), '/');
                Storage::disk('public')->makeDirectory($dir);
                Storage::disk('public')->putFileAs($dir, $file, basename($rel));
                $newFilePath = '/storage/' . $rel;
            } else {
                $abs = public_path(ltrim($filePath, '/'));
                File::ensureDirectoryExists(dirname($abs));
                // Move uploaded file to overwrite existing file
                $file->move(dirname($abs), basename($abs));
                $newFilePath = $filePath;
            }

            $selected->update([
                'file_path' => $newFilePath,
                'original_filename' => $originalName,
                'metadata' => array_merge($selected->metadata ?? [], [
                    'replaced_at' => now()->toDateTimeString(),
                    'replaced_via' => 'api',
                ]),
                'status' => 'pending',
                'sync_status' => 'pending',
            ]);

            return $this->successResponse(new PhotoSelectedResource($selected->fresh()), 'Selected photo updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getSelectedPhotosByBarcode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barcode_prefix' => 'required|string|min:4',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }

        $barcodePrefix = $request->query('barcode_prefix');

        $selected = PhotoSelected::where('barcode_prefix', $barcodePrefix)
            ->orderBy('created_at', 'desc')
            ->get();

        // if ($selected->isEmpty()) {
        //     return $this->errorResponse('No selected photos found for this barcode', Response::HTTP_NOT_FOUND);
        // }

        return $this->successResponse(
            PhotoSelectedResource::collection($selected)->resolve(),
            'Selected photos retrieved successfully'
        );
    }
}
