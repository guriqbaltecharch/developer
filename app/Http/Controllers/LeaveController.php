<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LeavePolicy;
use App\Models\Project;
use App\Models\User;
use App\Http\Helpers\ApiResponse;
use App\Http\Resources\ProjectResource;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class LeaveController extends Controller
{
	public function AddLeave(Request $request)
	{
		$user = auth()->user(); // ✅ Get logged-in user
		// ✅ Validate request
		$request->validate([
			'start_date' => 'required|date|after_or_equal:today',
			'end_date' => 'nullable|date|after_or_equal:start_date',
			'leave_type' => 'required|in:Full Leave,Short Leave,Half Day,Multiple Days Leave',
			'reason' => 'required|string|max:255',
			'status' => 'in:Pending,Approved,Rejected', // ✅ Default will be Pending
			'hours' => 'nullable|integer|min:1|max:12' // ✅ Only required for Short Leave
		]);
		// ✅ Set end_date automatically for Half Day, Full Leave, and Short Leave
		if (in_array($request->leave_type, ['Full Leave', 'Short Leave', 'Half Day'])) {
			$endDate = $request->start_date; // ✅ Auto-set end_date for single-day leaves
		} elseif ($request->leave_type === 'Multiple Days Leave' && isset($request->end_date)) {
			$endDate = $request->end_date; // ✅ Use user-provided end_date
		} else {
			return response()->json([
				'success' => false,
				'message' => "End date is required for Multiple Days Leave"
			], 400);
		}
		// ✅ Check if hours should be included for Short Leave
		$hours = ($request->leave_type === 'Short Leave') ? ($request->hours ?? null) : null;
		// ✅ Create leave entry in database
			$leave = LeavePolicy::create([
				'user_id' => $user->id, // ✅ Automatically set logged-in user's ID
				'start_date' => $request->start_date,
				'end_date' => $endDate, // ✅ Corrected end_date handling
				'leave_type' => $request->leave_type,
				'reason' => $request->reason,
				'status' => $request->status ?? 'Pending', // ✅ Default status
				'hours' => $hours // ✅ Store hours for Short Leave
			]);
		return response()->json([
			'success' => true,
			'message' => 'Leave request submitted successfully',
			'data' => $leave
		]);
	}
	
	public function getallLeavesForHr(Request $request)
    {
        // Fetch all leaves for all users with their associated user details (e.g., name)
        $leaves = LeavePolicy::with('user:id,name') // Load the user relationship and select only the id and name fields
                             ->get();

        // If no leaves are found, return a message saying "No leaves found"
        if ($leaves->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No leaves found',
                'data' => []
            ]);
        }

        // Return the response with leave data if found, including username
        $leaveData = $leaves->map(function ($leave) {
            return [
                'id' => $leave->id,
                'user_id' => $leave->user_id,
                'user_name' => $leave->user->name,  // Access the username from the loaded relationship
                'start_date' => $leave->start_date,
                'end_date' => $leave->end_date,
                'leave_type' => $leave->leave_type,
                'reason' => $leave->reason,
                'status' => $leave->status,
                'hours' => $leave->hours,
                'created_at' => $leave->created_at,
                'updated_at' => $leave->updated_at
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $leaveData
        ]);
    }
	
	public function getLeavesByemploye(Request $request)
	{
		// Get the authenticated user
        $user = auth()->user(); 

        // Fetch the leaves for the logged-in user, you can also add filtering or pagination here
        $leaves = LeavePolicy::where('user_id', $user->id)->get();

        if ($leaves->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No leaves found for this user',
                'data' => []
            ]);
        }

        // Return the response with leave data if found
        return response()->json([
            'success' => true,
            'data' => $leaves
        ]);
	}
	
	public function showmanagerLeavesForTeamemploye(Request $request)
	{
		 // Get the authenticated user (Manager)
        $user = auth()->user();

        // Check if the user is a Manager
        /*if ($user->role_id != 4) { // Assuming 4 is the Manager role_id
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this data.'
            ], 403);
        }*/

        // Get Manager's information (name, role, team)
        $managerInfo = [
            'id' => $user->id,
            'name' => $user->name,
            'role' => 'Manager', // You can replace this with dynamic role retrieval if needed
            'team_id' => $user->team_id,
            // You can add other manager-specific info here if needed
        ];

        // Get all employees who are part of the same team as the Manager
        $employees = User::where('team_id', $user->team_id)->get();

        // Get the leaves for all employees in the Manager's team
        $leaves = LeavePolicy::with('user:id,name,team_id') // Load the user details (name, team_id)
                             ->whereIn('user_id', $employees->pluck('id')) // Filter leaves for employees in the same team
                             ->get();

        // If no leaves are found, return a message
        if ($leaves->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No leaves found for your team.',
                'data' => []
            ]);
        }

        // Map the leave data to include the manager's info and employee's leave details
        $leaveData = $leaves->map(function ($leave) {
            return [
                'id' => $leave->id,
                'user_id' => $leave->user_id,
                'user_name' => $leave->user->name,  // Access user name from the relationship
                'start_date' => $leave->start_date,
                'end_date' => $leave->end_date,
                'leave_type' => $leave->leave_type,
                'reason' => $leave->reason,
                'status' => $leave->status,
                'hours' => $leave->hours,
                'created_at' => $leave->created_at,
                'updated_at' => $leave->updated_at
            ];
        });

        return response()->json([
            'success' => true,
            'manager' => $managerInfo, // Include Manager info in the response
            'data' => $leaveData // Include employee leaves
        ]);
	}
	
	// Method to approve or reject the leave request
    // Method to approve or reject the leave request
    public function approveLeave(Request $request)
    {
        // Validate the request to ensure each item in the array has `id` and `status`
        $request->validate([
            '*' => 'required|array', // Ensure each element in the array is an object
            '*.id' => 'required|exists:leavespolicy,id', // Ensure each leave ID exists in the leavespolicy table
            '*.status' => 'required|in:Approved,Rejected', // Status must be either Approved or Rejected
        ]);

        $user = auth()->user(); // Get the authenticated user (Manager)

        $updatedLeaves = [];

        // Loop through each leave object and update its status
        foreach ($request->all() as $leaveData) {
            $leave = LeavePolicy::find($leaveData['id']); // Find the leave record by ID

            // Update the leave status and the manager who approved it
            $leave->status = $leaveData['status'];
            $leave->approved_bymanager = $user->id; // Set the manager's ID (authenticated user)
            $leave->save(); // Save the updated leave record

            // Add the updated leave to the response array
            $updatedLeaves[] = $leave;
        }

        // Return success response with the updated leave data
        return response()->json([
            'success' => true,
            'message' => 'Leave status updated successfully',
            'data' => $updatedLeaves
        ]);
    }
}
