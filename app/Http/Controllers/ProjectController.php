<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\User;
use App\Http\Helpers\ApiResponse;
use App\Http\Resources\ProjectResource;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProjectController extends Controller
{
    public function index()
    {
        return ApiResponse::success('Projects fetched successfully', ProjectResource::collection(Project::with('client', 'salesTeam')->get()));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'sales_team_id' => 'required',
            'client_id' => 'required|exists:clients,id',
            'project_name' => 'required|string|max:255',
            'requirements' => 'nullable|string',
            'budget' => 'nullable|numeric',
            'deadline' => 'nullable|date',
            'total_hours' => 'required',
        ]);

        $project = Project::create($validatedData);
        return ApiResponse::success('Project created successfully', $project, 201);
    }

    public function assignProjectToManager(Request $request)
    {
        $validatedData = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'project_manager_id' => 'required|exists:users,id',
        ]);

        // $projectManager = User::where('id', $request->project_manager_id)->where('role_id', 5)->first();
        $projectManager = User::where('id', $request->project_manager_id)->first();

        if (!$projectManager) {
            return ApiResponse::error('Invalid Project Manager ID', [], 400);
        }

        $project = Project::findOrFail($request->project_id);
        $project->project_manager_id = $request->project_manager_id;
        $project->assigned_by = auth()->user()->id;
        $project->save();

        return ApiResponse::success('Project assigned to Project Manager successfully', $project->load('projectManager'));
    }
	
	public function assignProjectManagerProjectToEmployee(Request $request)
	{
		 // ✅ Validate Request Data
    $validatedData = $request->validate([
        'project_id' => 'required|exists:projects,id',
        'employee_ids' => 'required|array|min:1',
        'employee_ids.*' => 'exists:users,id'
    ]);

    // ✅ Fetch Project from Database
    $project = Project::find($validatedData['project_id']);

    if (!$project) {
        return ApiResponse::error('Invalid project_id. Project does not exist.', [], 404);
    }

    // ✅ Get Logged-in Project Manager ID
    $projectManagerId = auth()->user()->id;

    // ✅ Insert into `project_user` Table (Avoiding Duplicates)
    $insertedData = [];
    $alreadyAssigned = [];

    try {
        foreach ($validatedData['employee_ids'] as $employeeId) {
            // ✅ Check if the project is already assigned to this employee
            $exists = DB::table('project_user')
                ->where('project_id', $validatedData['project_id'])
                ->where('user_id', $employeeId)
                ->exists();

            if ($exists) {
                $alreadyAssigned[] = $employeeId; // ✅ Collect duplicate user_ids
                continue; // ✅ Skip this user but process the rest
            }

            // ✅ Insert only if not exists
            $insertedId = DB::table('project_user')->insertGetId([
                'project_id' => $validatedData['project_id'],
                'user_id' => $employeeId,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $insertedData[] = [
                'id' => $insertedId,  // ✅ Inserted increment ID
                'project_id' => $validatedData['project_id'],
                'user_id' => $employeeId
            ];
        }
    } catch (\Exception $e) {
        return ApiResponse::error('Database Error: ' . $e->getMessage(), [], 500);
    }

    // ✅ Prepare Response
    $responseMessage = 'Project assigned successfully';
    if (!empty($alreadyAssigned)) {
        $responseMessage .= '. But these users were already assigned: ' . implode(', ', $alreadyAssigned);
    }

    return ApiResponse::success($responseMessage, [
        'project_manager_id' => $projectManagerId, // ✅ Logged-in Project Manager ID
        'data' => $insertedData // ✅ Inserted records with `id`, `project_id`, `user_id`
    ]);
	}
	
	public function getProjectofEmployeeAssignbyProjectManager()
	{
		 // ✅ Get Logged-in Project Manager ID
		$projectManagerId = auth()->user()->id;
		// ✅ Fetch All Projects Assigned by This Project Manager
		$projects = Project::where('project_manager_id', $projectManagerId)
        ->with(['assignedEmployees' => function ($query) {
            $query->select('users.id', 'users.name', 'users.email'); // ✅ Only specific fields (No pivot)
        }])
        ->get(['id', 'project_name', 'client_id', 'deadline', 'project_manager_id']);

		// ✅ If No Projects Found
		if ($projects->isEmpty()) {
			return ApiResponse::error('No projects found for this Project Manager.', [], 404);
		}
// ✅ Return Response
    return ApiResponse::success('Projects fetched successfully', [
        'project_manager_id' => $projectManagerId,
        'projects' => $projects
    ]);
	}

public function getUserProjects()
{
    $user = auth()->user();

    // ✅ Fetch projects with full client data & pivot (assigned_at)
    $projects = $user->assignedProjects()
        ->with('client') // ✅ Fetch full client data
        ->get()
        ->map(function ($project) {
            return [
                'id' => $project->id,
                'project_name' => $project->project_name,
                'budget' => $project->budget,
                'requirements' => $project->requirements,
                'deadline' => $project->deadline,
                'created_at' => Carbon::parse($project->created_at)->toDateString(), // ✅ Keep only date
                'updated_at' => Carbon::parse($project->updated_at)->toDateString(), // ✅ Keep only date
                'client' => $project->client ?? ['message' => 'No Client Found'], // ✅ Return full client data
                'pivot' => [
                    'user_id' => $project->pivot->user_id,
                    'project_id' => $project->pivot->project_id,
                    'assigned_at' => $project->pivot->created_at
                        ? Carbon::parse($project->pivot->created_at)->toDateString()  // ✅ Keep only date
                        : 'Not Assigned'
                ]
            ];
        });

    return ApiResponse::success('User projects fetched successfully', $projects);
}

public function getAssignedProjects()
    {
        $user = auth()->user();

        // if ($user->role_id != 5) {
        //     return ApiResponse::error('Only Project Managers can view assigned projects', [], 403);
        // }

        $projects = Project::where('project_manager_id', $user->id)->with('client', 'assignedBy')->get();

        return ApiResponse::success('Projects fetched successfully', $projects);
    }

	public function update(Request $request, $id)
    {
        $project = Project::find($id);
		if (!$project) {
            return ApiResponse::error('Project not found', [], 404);
        }
		$validatedData = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'project_name' => 'required|string|max:255',
            'requirements' => 'nullable|string',
            'budget' => 'nullable|numeric',
            'deadline' => 'nullable|date'
        ]);
		$project->update($validatedData);
		return ApiResponse::success('Project updated successfully', new ProjectResource($project));
    }

    public function destroy($id)
    {
        $project = Project::find($id);
		if (!$project) {
            return ApiResponse::error('Project not found', [], 404);
        }
		$project->delete();
        return ApiResponse::success('Project deleted successfully');
    }
	
	public function assignUsersToProject(Request $request, $projectId)
	{
		$project = Project::find($projectId);
		if (!$project) {
			return ApiResponse::error('Project not found', [], 404);
		}
		$request->validate([
        'user_ids' => 'required|array',
        'user_ids.*' => 'exists:users,id'
		]);
		// Attach users to the project (if already assigned, it won't duplicate)
		$project->assignedUsers()->sync($request->user_ids);
		return ApiResponse::success('Users assigned successfully', $project->load('assignedUsers'));
	}

// Projects with  EMployess by all project manager 
public function getAssignedAllProjects()
{
    $user = auth()->user();
    // Fetch all projects with related client, assignedBy, assignedUsers, and projectManager
    $projects = Project::with('client', 'assignedBy', 'assignedUsers:id,name,email', 'projectManager:id,name')->get();
	// Format the response to ensure proper structure
    // Remove pivot from assigned users
    $projects = $projects->map(function ($project) {
        return [
            'id' => $project->id,
            'project_name' => $project->project_name,
            'budget' => $project->budget,
            'deadline' => $project->deadline,
            'client' => $project->client,
            'assigned_by' => $project->assignedBy,
            'project_manager' => $project->projectManager 
                ? ['id' => $project->projectManager->id, 'name' => $project->projectManager->name] 
                : 'No project manager assigned',
            'assigned_users' => $project->assignedUsers->makeHidden('pivot')->isEmpty() 
                ? 'Project not assign to anyone yet' 
                : $project->assignedUsers
        ];
    });
	return ApiResponse::success('Projects fetched successfully', $projects);
}

/*public function getProjectEmployee()
{
	$user = auth()->user();
	$projects = Project::where('project_manager_id', $user->id)
        ->with([
            'client:id,name', // Get only client id & name
            'projectManager:id,name' // Get project manager id & name
        ])
        ->get(['id', 'project_name', 'client_id']); // Fetch only required fields

    return ApiResponse::success('Projects fetched successfully', $projects);
    //return response()->json(['message' => 'Test']);
}*/

public function getProjectManagerEmployee()
{
    $user = auth()->user(); // Get logged-in user

    if (!$user->team_id) {
        return response()->json([
            'success' => false,
            'message' => 'Team ID not found for this user.',
            'data' => []
        ]);
    }

    // Fetch all employees in the same team, excluding the logged-in manager
    $employees = User::where('team_id', $user->team_id)
        ->where('id', '!=', $user->id) // Exclude logged-in user
        ->select('id', 'name', 'email', 'profile_pic', 'role_id')
        ->get();

    return response()->json([
        'success' => true,
        'message' => $employees->isEmpty() ? 'No employees found for this team.' : 'Employees fetched successfully',
        'team_id' => $user->team_id,
        'project_manager_id' => $user->id, // Add Project Manager ID
        'employees' => $employees
    ]);
}

}