<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use App\Http\Resources\UserResource;
use App\Http\Helpers\ApiResponse;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
  public function addPerformaSheets(Request $request)
{
    $user = auth()->user();

    try {
        $validatedData = $request->validate([
            'data' => 'required|array',
            'data.*.project_id' => [
                'required',
                Rule::exists('project_user', 'project_id')->where(fn($query) => $query->where('user_id', $user->id))
            ],
            'data.*.date' => 'required|date_format:Y-m-d',
            'data.*.time' => 'required|date_format:H:i',
            'data.*.work_type' => 'required|string|max:255',
            'data.*.activity_type' => 'required|string|max:255',
            'data.*.narration' => 'nullable|string'
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return ApiResponse::error('Validation failed', $e->errors(), 422);
    }

    $insertedRecords = [];
    $projectIds = [];

    foreach ($validatedData['data'] as $record) {
        // ✅ Convert time (HH:mm) to total decimal hours
        list($hours, $minutes) = explode(':', $record['time']);
        $timeInHours = (int)$hours + ((int)$minutes / 60); // Convert minutes to decimal

        // ✅ Store Performa Sheet Entry (JSON format)
        $insertedRecords[] = PerformaSheet::create([
            'user_id' => $user->id,
            'data' => json_encode($record) // ✅ Store data as JSON
        ]);

        // ✅ Keep track of project IDs that need to be updated
        if (!in_array($record['project_id'], $projectIds)) {
            $projectIds[] = $record['project_id'];
        }
    }

    // ✅ Update `total_working_hours` for each project
    foreach ($projectIds as $projectId) {
        // ✅ Get total sum of all working hours for this `project_id`
        $totalWorkingHours = PerformaSheet::whereRaw("JSON_EXTRACT(data, '$.project_id') = ?", [$projectId])
            ->get()
            ->sum(function ($performa) {
                $performaData = json_decode($performa->data, true);
                list($h, $m) = explode(':', $performaData['time']);
                return (int)$h + ((int)$m / 60);
            });

        // ✅ Update the `total_working_hours` in the `projects` table
        Project::where('id', $projectId)->update([
            'total_working_hours' => $totalWorkingHours
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => count($insertedRecords) . ' Performa Sheets added successfully',
        'data' => $insertedRecords
    ]);
}


    public function index()
    {
        $users = User::with(['team', 'role'])->get();
        return ApiResponse::success('Users fetched successfully', UserResource::collection($users));
    }

    public function projectManger()
    {
        $users = User::where('role_id',5)->get();
        return ApiResponse::success('Project Manger fetched successfully', UserResource::collection($users));
    }

    public function show($id)
    {
        $user = User::with(['team', 'role'])->find($id);

        if (!$user) {
            return ApiResponse::error('User not found', [], 404);
        }

        return ApiResponse::success('User details fetched successfully', new UserResource($user));
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return ApiResponse::error('User not found', [], 404);
        }

        $user->delete();
        return ApiResponse::success('User deleted successfully');
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return ApiResponse::error('User not found', [], 404);
        }

        try {
            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'phone_num' => 'nullable|string|max:15',
                'emergency_phone_num' => 'nullable|string|max:15',
                'address' => 'nullable|string',
                'team_id' => 'nullable|exists:teams,id',
                'role_id' => 'nullable',
                'role_id.*' => 'exists:roles,id',
                'profile_pic' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation Error', $e->errors(), 422);
        }

        $user->update($request->only(['name', 'email', 'role_id', 'phone_num', 'address', 'team_id', 'emergency_phone_num']));
        $user->refresh(); // Ensure new values are reflected

        if ($request->hasFile('profile_pic')) {
            $file = $request->file('profile_pic');
            $filename = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/profile_pics', $filename);
            $user->profile_pic = $filename;
            $user->save(); // Explicitly save the changes
        }

        if ($request->has('roles')) {
            $user->roles()->sync($request->roles);
        }

        return ApiResponse::success('User updated successfully', new UserResource($user->fresh()));
    }


}
