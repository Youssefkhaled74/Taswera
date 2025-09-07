<?php

namespace App\Http\Controllers\Api;

use App\Models\Frame;
use App\Http\Resources\FrameResource;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;

class FrameController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $path = $request->file('photo')->store('frames', 'public');

        $frame = Frame::create([
            'name' => $request->input('name'),
            'photo' => $path,
        ]);

        return response()->json([
            'message' => 'Frame created successfully',
            'data' => new FrameResource($frame),
        ], Response::HTTP_CREATED);
    }

    public function index()
    {
        $frames = Frame::all();
        return response()->json([
            'message' => 'Frames retrieved successfully',
            'data' => FrameResource::collection($frames),
        ], Response::HTTP_OK);
    }

    public function show($id)
    {
        $frame = Frame::findOrFail($id);
        return response()->json([
            'message' => 'Frame retrieved successfully',
            'data' => new FrameResource($frame),
        ], Response::HTTP_OK);
    }

    public function destroyMany(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:frames,id',
        ]);
        $deleted = \App\Models\Frame::whereIn('id', $request->ids)->delete();
        return response()->json([
            'message' => 'Frames deleted successfully',
            'deleted_count' => $deleted,
        ]);
    }
}
