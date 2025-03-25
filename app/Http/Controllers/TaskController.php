<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Project;
use Illuminate\Http\Request;
use App\Http\Resources\TaskResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function AddTasks(Request $request)
    {
        $user = Auth::user(); // ✅ Get logged-in user ID
        $userId = $user->id;

        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:To do,In Progress,Completed,Cancel',
            'project_id' => 'nullable|exists:projects,id',
            'hours' => 'nullable|integer|min:1',
            'deadline' => 'nullable|date'
        ]);

        // ✅ If a project_id is provided, check if the logged-in user is in the JSON array
        if (!empty($validatedData['project_id'])) {
            $project = Project::find($validatedData['project_id']);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found.'
                ], 404);
            }

            // ✅ Decode project_manager_id JSON array
            $assignedManagers = json_decode($project->project_manager_id, true);

            if (!is_array($assignedManagers) || !in_array($userId, $assignedManagers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not assigned to this project.'
                ], 403);
            }
        }

        // ✅ Always set project_manager_id as logged-in user ID
        $validatedData['project_manager_id'] = $userId;

        $task = Task::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'data' => new TaskResource($task)
        ]);
    }
	
	public function getAllTaskofProjectById($id)
	{
         $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found.'
            ], 404);
        }

        // ✅ Decode `project_manager_id` JSON array
        $projectManagers = json_decode($project->project_manager_id, true);

        // ✅ Get all tasks related to this project
        $tasks = Task::where('project_id', $id)
            ->with('projectManager:id,name') // ✅ Fetch Project Manager details
            ->get();

        // ✅ Calculate total task hours
        $totalTaskHours = $tasks->sum('hours');

        // ✅ Format tasks array
        $formattedTasks = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'hours' => $task->hours,
                'deadline' => $task->deadline,
                'project_manager' => $task->projectManager ? [
                    'id' => $task->projectManager->id,
                    'name' => $task->projectManager->name
                ] : null
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Project fetched successfully.',
            'data' => [
                'id' => $project->id,
                'project_name' => $project->project_name,
                'deadline' => $project->deadline,
                'total_hours' => $project->total_hours,
                'total_working_hours' => $project->total_working_hours,
                'total_task_hours' => $totalTaskHours, // ✅ Add total hours of all tasks
                'project_managers' => $projectManagers, // ✅ Project managers as an array
                'tasks' => $formattedTasks, // ✅ Task details with project managers
                'created_at' => $project->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $project->updated_at->format('Y-m-d H:i:s')
            ]
        ]);
    }
	
	public function getEmployeTasksbyProject(Request $request)
	{
		$user = Auth::user(); // ✅ Get logged-in user ID
        $userId = $user->id;

        // ✅ Validate Request Data
        $validatedData = $request->validate([
            'project_id' => 'required|exists:projects,id'
        ]);

        $projectId = $validatedData['project_id'];

        // ✅ Step 1: Get `project_manager_id` from `project_user`
        $projectManager = DB::table('project_user')
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->value('project_manager_id'); // ✅ Get the project_manager_id directly

        if (!$projectManager) {
            return response()->json([
                'success' => false,
                'message' => 'No project manager found for this project and user.'
            ], 403);
        }

        // ✅ Step 2: Fetch tasks where `project_id` and `project_manager_id` match
        $tasks = Task::where('project_id', $projectId)
            ->where('project_manager_id', $projectManager)
            ->get();

        if ($tasks->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No tasks found for this project and project manager.',
                'data' => []
            ]);
        }

        // ✅ Format tasks array
        $formattedTasks = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'hours' => $task->hours,
                'deadline' => $task->deadline
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Tasks fetched successfully.',
            'project_manager_id' => $projectManager, // ✅ Return fetched project_manager_id
            'data' => $formattedTasks
        ]);
    }
	
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
