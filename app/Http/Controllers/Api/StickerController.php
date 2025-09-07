<?php

namespace App\Http\Controllers\Api;

use App\Models\Sticker;
use App\Http\Resources\StickerResource;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;

class StickerController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $path = $request->file('photo')->store('stickers', 'public');

        $sticker = Sticker::create([
            'name' => $request->input('name'),
            'photo' => $path,
        ]);

        return response()->json([
            'message' => 'Sticker created successfully',
            'data' => new StickerResource($sticker),
        ], Response::HTTP_CREATED);
    }

    public function index()
    {
        $stickers = Sticker::all();
        return response()->json([
            'message' => 'Stickers retrieved successfully',
            'data' => StickerResource::collection($stickers),
        ], Response::HTTP_OK);
    }

    public function show($id)
    {
        $sticker = Sticker::findOrFail($id);
        return response()->json([
            'message' => 'Sticker retrieved successfully',
            'data' => new StickerResource($sticker),
        ], Response::HTTP_OK);
    }

    public function destroyMany(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:stickers,id',
        ]);
        $deleted = \App\Models\Sticker::whereIn('id', $request->ids)->delete();
        return response()->json([
            'message' => 'Stickers deleted successfully',
            'deleted_count' => $deleted,
        ]);
    }
}
