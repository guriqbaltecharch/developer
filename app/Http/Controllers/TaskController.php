<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Project;
use Illuminate\Http\Request;
use App\Http\Resources\TaskResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function AddTasks(Request $request)
    {
        try {
            $user = Auth::user(); // ✅ Get logged-in user ID

            // ✅ Validate request data
            $validatedData = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'required|in:To do,In Progress,Completed,Cancel',
                'project_id' => 'required|exists:projects,id',
                'hours' => 'nullable|integer|min:1',
                'deadline' => 'nullable|date'
            ]);

            // ✅ Get the project from the database
            $project = Project::find($validatedData['project_id']);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found.'
                ], 404);
            }

            // ✅ Get the current total_hours from the project
            $currentHours = $project->total_hours ?? 0; // ✅ If null, default to 0

            // ✅ Add new hours to the existing total_hours
            $newTotalHours = $currentHours + ($validatedData['hours'] ?? 0);

            // ✅ Create the task
            $task = Task::create([
                'title' => $validatedData['title'],
                'description' => $validatedData['description'],
                'status' => $validatedData['status'],
                'project_id' => $validatedData['project_id'],
                'project_manager_id' => $user->id, // ✅ Assign logged-in user as project manager
                'hours' => $validatedData['hours'],
                'deadline' => $validatedData['deadline']
            ]);

            // ✅ Step 1: Find the highest `deadline` for the same `project_id`
            $highestDeadline = Task::where('project_id', $validatedData['project_id'])
                ->max('deadline');

            // ✅ Step 2: Update the `deadline` in the projects table
            if ($highestDeadline) {
                $project->update([
                    'total_hours' => $newTotalHours, // ✅ Update total hours
                    'deadline' => $highestDeadline  // ✅ Update with the latest deadline
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Task created successfully and project deadline updated.',
                'project' => [
                    'id' => $project->id,
                    'name' => $project->project_name,
                    'updated_total_hours' => $newTotalHours,
                    'updated_deadline' => $highestDeadline
                ],
                'task' => $task
            ]);

        } catch (\Exception $e) {
            Log::error('Error adding task: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
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
		try {
            $user = Auth::user(); // ✅ Get logged-in user ID
            $userId = $user->id;

            // ✅ Validate Request Data
            $validatedData = $request->validate([
                'project_id' => 'required|exists:projects,id'
            ]);

            $projectId = $validatedData['project_id'];

            // ✅ Step 1: Get `project_manager_id` from `project_user`
            $projectManagerId = DB::table('project_user')
                ->where('project_id', $projectId)
                ->where('user_id', $userId)
                ->value('project_manager_id'); // ✅ Get the project_manager_id directly

            if (!$projectManagerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No project manager found for this project and user.'
                ], 403);
            }

            // ✅ Step 2: Get Project Manager's Details
            $projectManager = User::find($projectManagerId);

            if (!$projectManager) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project Manager not found in users table.',
                    'project_manager_id' => $projectManagerId
                ], 404);
            }

            // ✅ Step 3: Fetch Project Details
            $project = Project::find($projectId);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found.'
                ], 404);
            }

            // ✅ Step 4: Fetch tasks where `project_id` and `project_manager_id` match
            $tasks = Task::where('project_id', $projectId)
                ->where('project_manager_id', $projectManagerId)
                ->get();

            if ($tasks->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No tasks found for this project and project manager.',
                    'project' => [
                        'id' => $project->id,
                        'name' => $project->project_name,
                        'deadline' => $project->deadline,
                        'total_hours' => $project->total_hours,
                        'total_working_hours' => $project->total_working_hours
                    ],
                    'project_manager' => [
                        'id' => $projectManagerId,
                        'name' => $projectManager->name
                    ],
                    'data' => []
                ]);
            }

            // ✅ Step 5: Format tasks array
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
                'project' => [
                    'id' => $project->id,
                    'name' => $project->project_name,
                    'deadline' => $project->deadline,
                    'total_hours' => $project->total_hours,
                    'total_working_hours' => $project->total_working_hours
                ],
                'project_manager' => [
                    'id' => $projectManagerId,
                    'name' => $projectManager->name
                ],
                'data' => $formattedTasks
            ]);

        } catch (\Exception $e) {
            // ✅ Log the error for debugging
            Log::error('Error fetching tasks: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
	
	public function ApproveTaskofProject(Request $request)
    {
        try {
            // ✅ Validate request body
            $validatedData = $request->validate([
                'id' => 'required|exists:tasks,id',
                'status' => 'required|in:To do,In Progress,Completed,Cancel'
            ]);

            // ✅ Fetch the task
            $task = Task::find($validatedData['id']);

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found.'
                ], 404);
            }

            // ✅ Get the logged-in user (Project Manager)
            $projectManagerId = Auth::user()->id;

            // ✅ Ensure only assigned Project Manager can update status
            /*if ($task->project_manager_id != $projectManagerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to approve this taskxxxx.'
                ], 403);
            }*/

            // ✅ Update task status
            $task->status = $validatedData['status'];
            $task->save();

            return response()->json([
                'success' => true,
                'message' => 'Task status updated successfully.',
                'data' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'updated_by' => [
                        'id' => $projectManagerId,
                        'name' => Auth::user()->name
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating task status: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
	
	public function EditTasks(Request $request, $id)
    {
		try {
            // ✅ Validate request
            $validatedData = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|nullable|string',
                'hours' => 'sometimes|integer|min:1',
                'deadline' => 'sometimes|date'
            ]);

            // ✅ Step 1: Find the task by ID
            $task = Task::find($id);

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found.'
                ], 404);
            }

            // ✅ Get the project associated with this task
            $project = Project::find($task->project_id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found.'
                ], 404);
            }

            // ✅ Step 2: Adjust `hours` in `projects` table
            if (isset($validatedData['hours'])) {
                $previousHours = $task->hours ?? 0; // ✅ Get existing task hours (if null, default to 0)
                $newHours = $validatedData['hours'];

                // ✅ Calculate correct total hours (subtract previous, add new)
                $newTotalHours = max(0, ($project->total_hours - $previousHours) + $newHours);

                // ✅ Update project total hours
                $project->update(['total_hours' => $newTotalHours]);

                // ✅ Update task with new hours
                $task->hours = $newHours;
            }

            // ✅ Step 3: Update task fields dynamically
            $task->update($validatedData);

            // ✅ Step 4: Get the highest `deadline` from tasks for the same `project_id`
            $highestDeadline = Task::where('project_id', $task->project_id)->max('deadline');

            // ✅ Step 5: Update project deadline with highest deadline
            if ($highestDeadline) {
                $project->update(['deadline' => $highestDeadline]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully, project details also updated.',
                'project' => [
                    'id' => $project->id,
                    'name' => $project->project_name,
                    'updated_total_hours' => $project->total_hours,
                    'updated_deadline' => $highestDeadline
                ],
                'task' => $task
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating task: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
	
	public function DeleteTasks(Request $request, $id)
    {
		try {
            // ✅ Step 1: Find the task by ID
            $task = Task::find($id);

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found.'
                ], 404);
            }

            // ✅ Get the project associated with this task
            $project = Project::find($task->project_id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found.'
                ], 404);
            }

            // ✅ Step 2: Subtract task `hours` from `projects` table
            $previousHours = $task->hours ?? 0; // ✅ Get existing task hours (if null, default to 0)
            $newTotalHours = max(0, $project->total_hours - $previousHours); // ✅ Ensure it never goes below 0
            $project->update(['total_hours' => $newTotalHours]);

            // ✅ Step 3: Delete the task
            $task->delete();

            // ✅ Step 4: Get the highest `deadline` from remaining tasks for the same `project_id`
            $highestDeadline = Task::where('project_id', $task->project_id)->max('deadline');

            // ✅ Step 5: Update project deadline with highest deadline or set null if no tasks left
            $project->update(['deadline' => $highestDeadline]);

            return response()->json([
                'success' => true,
                'message' => 'Task deleted successfully and project details updated.',
                'project' => [
                    'id' => $project->id,
                    'name' => $project->project_name,
                    'updated_total_hours' => $project->total_hours,
                    'updated_deadline' => $highestDeadline
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting task: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
