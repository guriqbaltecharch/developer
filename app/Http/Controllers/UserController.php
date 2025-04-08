<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PerformaSheet;
use App\Models\Project;
use App\Models\Role;
use App\Http\Resources\UserResource;
use App\Http\Helpers\ApiResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserController extends Controller
{
   public function store(Request $request)
	{
    try {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'team_id' => 'nullable|exists:teams,id',
            'phone_num' => 'nullable|string|max:15',
            'emergency_phone_num' => 'nullable|string|max:15',
            'address' => 'nullable|string',
            'role_id' => 'required|exists:roles,id',
            'profile_pic' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'profile_pic_name' => 'nullable|string' // Accepting image name in JSON
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return ApiResponse::error('Validation failed', $e->errors(), 422);
    }

    // ✅ Create the user
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'address' => $request->address,
        'phone_num' => $request->phone_num,
        'emergency_phone_num' => $request->emergency_phone_num,
        'password' => Hash::make($request->password),
        'team_id' => $request->team_id,
        'role_id' => $request->role_id,
        'profile_pic' => $request->profile_pic_name // Save image name from JSON
    ]);

    // ✅ Save file only if uploaded
    if ($request->hasFile('profile_pic')) {
        $file = $request->file('profile_pic');
        $filename = time() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('public/profile_pics', $filename);

        $user->profile_pic = $filename; // Update stored image name
        $user->save();
    }

    return ApiResponse::success('User created successfully', new UserResource($user), 201);
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
	

public function GetFullProileEmployee($id)
{
    $user = User::with(['team', 'role'])->find($id);

    if (!$user) {
        return ApiResponse::error('User not found', [], 404);
    }

    // Fetch all project-user mappings
    $projectUserData = DB::table('project_user')
        ->leftJoin('users as pm', 'project_user.project_manager_id', '=', 'pm.id')
        ->leftJoin('projects', 'project_user.project_id', '=', 'projects.id')
        ->select(
            'project_user.user_id',
            'project_user.project_id',
            'projects.project_name',
            'project_user.project_manager_id',
            'pm.name as project_manager_name',
            'project_user.created_at',
            'project_user.updated_at'
        )
        ->where('project_user.user_id', $id)
        ->get();

    // Get performa data (only approved)
    $performaSheets = DB::table('performa_sheets')
        ->where('user_id', $id)
        ->where('status', 'approved')
        ->get();

    $activityData = [];

    foreach ($performaSheets as $row) {
        $decoded = json_decode($row->data, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        // Log decoded data to check structure
        \Log::info('Decoded Sheet Data', ['sheet_id' => $row->id, 'decoded' => $decoded]);

        $entries = isset($decoded[0]) ? $decoded : [$decoded];

        foreach ($entries as $entry) {
            if (!isset($entry['activity_type'], $entry['time'])) continue;

            $activityType = $entry['activity_type'];
            $projectId = $entry['project_id'] ?? null; // treat null as Inhouse
            $time = $entry['time'];

            $timeParts = explode(':', $time);
            if (count($timeParts) !== 2) continue;

            $minutes = ((int)$timeParts[0] * 60) + (int)$timeParts[1];

            // Grouping by project_id and activity_type
            if (!isset($activityData[$projectId])) {
                $activityData[$projectId] = [];
            }

            if (!isset($activityData[$projectId][$activityType])) {
                $activityData[$projectId][$activityType] = 0;
            }

            $activityData[$projectId][$activityType] += $minutes;
        }
    }

    // Create a new "inhouse" project block if needed
    $projectUserDataArray = $projectUserData->toArray();

    // Handle Inhouse (project_id = null) manually
    if (isset($activityData[null])) {
        $projectUserDataArray[] = (object) [
            'project_id' => null,
            'project_name' => 'Inhouse',
            'project_manager_id' => null,
            'project_manager_name' => null,
            'user_id' => $id,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    // Attach activity totals to each project
    $finalProjects = collect($projectUserDataArray)->transform(function ($project) use ($activityData) {
        $pid = $project->project_id;
        $activities = [];

        if (isset($activityData[$pid])) {
            foreach ($activityData[$pid] as $type => $minutes) {
                $h = floor($minutes / 60);
                $m = $minutes % 60;

                $activities[] = [
                    'activity_type' => $type,
                    'total_hours' => sprintf('%02d:%02d', $h, $m),
                ];
            }
        }

        $project->activities = $activities;
        return $project;
    });

    return ApiResponse::success('User details fetched successfully', [
        'user' => new UserResource($user),
        'project_user' => $finalProjects,
    ]);
}







}
