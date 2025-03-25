<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use App\Http\Resources\TaskResource;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function AddTasks(Request $request)
    {
        $user = Auth::user(); // ✅ Get logged-in user

        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:To do,In Progress,Completed,Cancel',
            'project_id' => 'nullable|exists:projects,id',
            //'project_manager_id' => 'nullable|exists:users,id', // ✅ Allow passing project_manager_id
            'hours' => 'nullable|integer|min:1',
            'deadline' => 'nullable|date'
        ]);

        // ✅ If project_manager_id is not provided, set it to the authenticated user's ID
        $validatedData['project_manager_id'] = $validatedData['project_manager_id'] ?? $user->id;

        $task = Task::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'data' => new TaskResource($task)
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
