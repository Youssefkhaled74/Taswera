<?php

namespace App\Http\Controllers\Api;

use App\Models\Shift;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShiftResource;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ShiftController extends Controller
{
    use ApiResponse;
    public function index()
    {
        $shifts = Shift::where('branch_id', auth('branch-manager')->user()->branch_id)->get();
        return $this->successResponse(ShiftResource::collection($shifts), 'Records fetched successfully');
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'branch_id' => 'required',
            'name' => 'required',
            'from' => 'required | date_format:H:i',
            'to' => 'required | date_format:H:i',
            // 'status' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }
        $shift = new Shift();
        $shift->branch_id = auth('branch-manager')->user()->branch_id;
        $shift->name = $request->input('name');
        $shift->from = $request->input('from');
        $shift->to = $request->input('to');
        $shift->save();
        return $this->successResponse(new ShiftResource($shift), 'Shift created successfully');
    }

    public function delete($id)
    {
        $shift = Shift::find($id);
        if (!$shift) {
            return $this->errorResponse('Shift not found', Response::HTTP_NOT_FOUND);
        }
        $shift->delete();
        return $this->successResponse(null, 'Shift deleted successfully');
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            // 'branch_id' => 'required',
            'name' => 'required',
            'from' => 'required | date_format:H:i',
            'to' => 'required | date_format:H:i',
            // 'status' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }
        $shift = Shift::find($id);
        if (!$shift) {
            return response()->json(['message' => 'Shift not found'], 404);
        }
        $shift->branch_id = auth('branch-manager')->user()->branch_id;
        $shift->name = $request->input('name');
        $shift->from = $request->input('from');
        $shift->to = $request->input('to');
        $shift->save();
        return $this->successResponse(new ShiftResource($shift), 'Shift updated successfully');
    }
}
