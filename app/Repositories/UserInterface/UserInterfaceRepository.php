<?php

namespace App\Repositories\UserInterface;

use App\Models\User;
use App\Models\Photo;
use App\Http\Resources\PhotoResource;
use Illuminate\Support\Facades\DB;

class UserInterfaceRepository implements UserInterfaceRepositoryInterface
{
    /**
     * Get user photos by barcode and phone number
     *
     * @param string $barcode
     * @param string $phoneNumber
     * @return array
     */
    public function getUserPhotos(string $barcode, string $phoneNumber): array
    {
        // Get user by barcode and phone number
        $user = User::where('barcode', 'LIKE', $barcode . '%')
            ->where('phone_number', $phoneNumber)
            ->first();

        if (!$user) {
            return [];
        }

        // Get all photos for the user with relationships
        $photos = Photo::with(['staff', 'branch'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'user' => [
                'barcode' => $user->barcode,
                'phone_number' => $user->phone_number
            ],
            'photos' => PhotoResource::collection($photos)->resolve()
        ];
    }
} 