<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\User;
use App\Http\Helpers\ApiResponse;
use App\Http\Resources\ProjectResource;

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
            'deadline' => 'nullable|date'
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

    public function assignProjectToEmployee(Request $request)
    {
        $validatedData = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:users,id'
        ]);

        $project = Project::findOrFail($request->project_id);

        // Check if the authenticated user is the Project Manager of this project
        if (auth()->user()->id !== $project->project_manager_id) {
            return ApiResponse::error('You are not authorized to assign employees to this project', [], 403);
        }

        $project->assignedEmployees()->sync($request->employee_ids);

        return ApiResponse::success('Project assigned to Employees successfully', $project->load('assignedEmployees'));
    }

    public function getUserProjects()
    {
        $user = auth()->user();
        $projects = $user->assignedProjects()->with('client')->get();

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

}
