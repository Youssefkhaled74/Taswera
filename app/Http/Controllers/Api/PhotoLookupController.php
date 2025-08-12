<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhotoResource;
use App\Models\Photo;
use App\Models\User;
use App\Models\PhotoSelection;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
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
            'photo_ids.*.quantity' => 'required|integer|min:1',
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

                // Replace: remove previous selections and previous clones for this barcode
                PhotoSelection::where('user_id', $user->id)
                    ->where('barcode_prefix', $barcodePrefix)
                    ->delete();

                Photo::where('user_id', $user->id)
                    ->where('barcode_prefix', $barcodePrefix)
                    ->whereNotNull('metadata->cloned_from')
                    ->delete();

                $clonedPhotos = [];

                foreach ($photoItems as $item) {
                    $photoId = (int) $item['id'];
                    $quantity = (int) $item['quantity'];
                    $original = $originalPhotos[$photoId];

                    // Insert one selection row per quantity (duplicate rows)
                    for ($s = 0; $s < $quantity; $s++) {
                        PhotoSelection::create([
                            'user_id' => $user->id,
                            'original_photo_id' => $photoId,
                            'barcode_prefix' => $barcodePrefix,
                            'quantity' => 1,
                            'metadata' => ['source' => 'user_interface'],
                        ]);
                    }

                    for ($i = 0; $i < $quantity; $i++) {
                        $cloned = Photo::create([
                            'user_id' => $user->id,
                            'barcode_prefix' => $barcodePrefix,
                            'file_path' => $original->file_path,
                            'original_filename' => $original->original_filename,
                            'uploaded_by' => $original->uploaded_by,
                            'branch_id' => $original->branch_id,
                            'is_edited' => $original->is_edited,
                            'thumbnail_path' => $original->thumbnail_path,
                            'status' => 'pending',
                            'sync_status' => 'pending',
                            'metadata' => array_merge($original->metadata ?? [], [
                                'cloned_from' => $original->id,
                                'created_from' => 'photo_selection',
                                'clone_index' => $i + 1,
                            ]),
                        ]);
                        $clonedPhotos[] = $cloned;
                    }
                }

                $eloquentCloned = \Illuminate\Database\Eloquent\Collection::make($clonedPhotos);
                $eloquentCloned->load(['staff', 'branch']);

                return $this->successResponse([
                    'cloned_count' => count($clonedPhotos),
                    'photos' => PhotoResource::collection($eloquentCloned)->resolve(),
                ], 'Photos cloned and recorded successfully');
            });
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
