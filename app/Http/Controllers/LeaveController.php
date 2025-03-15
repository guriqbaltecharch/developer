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
}
