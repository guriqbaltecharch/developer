<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\User;
use App\Http\Helpers\ApiResponse;
use App\Http\Resources\ProjectResource;
use App\Models\PerformaSheet;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;  
use Illuminate\Support\Facades\Auth;


class GraphController extends Controller
{
  
public function GraphTotalWorkingHour(Request $request)
{
    // Request se start aur end date lena (Format: YYYY-MM-DD)
    $startDate = $request->input('start_date');
    $endDate = $request->input('end_date');

    // Query ko filter karna based on date range
    $dataQuery = DB::table('performa_sheets')->select('id', 'data', 'status')->where('status', 'approved'); 
    $data = $dataQuery->get();

    // Initialize arrays and total time counters
    $times = [];
    $totalBillableMinutes = 0;
    $totalNonBillableMinutes = 0;
    $totalInhouseMinutes = 0;

    // Loop through each row and process the data
    foreach ($data as $row) {
        // Decode JSON data
        $decodedString = json_decode($row->data, true);
        if ($decodedString === null) {
            Log::warning("Invalid JSON format in data field for ID: {$row->id}");
            continue;
        }

        // To handle the case where data might be double-escaped
        if (is_string($decodedString)) {
            $decodedString = json_decode($decodedString, true);
        }

        // Check if decoded data is an array and if 'date' and 'time' exist
        if (!isset($decodedString['date']) || !isset($decodedString['time'])) {
            Log::warning("Missing date or time in data field for ID: {$row->id}");
            continue;
        }

        // Convert the date to a format for comparison (YYYY-MM-DD)
        $recordDate = $decodedString['date'];

        // Check if the record's date is within the specified range
        if (($startDate && strtotime($recordDate) < strtotime($startDate)) || ($endDate && strtotime($recordDate) > strtotime($endDate))) {
            continue;  // Skip the record if it's outside the date range
        }

        // Store data for response
        $times[] = [
            'id' => $row->id,
            'time' => $decodedString['time'],
            'activity_type' => $decodedString['activity_type'] ?? 'Unknown',
            'date' => $decodedString['date'],
			'status' => $row->status,
        ];

        // Convert HH:MM time into total minutes
        $timeParts = explode(':', $decodedString['time']);
        if (count($timeParts) !== 2) {
            Log::warning("Invalid time format for ID: {$row->id}, Time: {$decodedString['time']}");
            continue;
        }

        $hours = intval($timeParts[0]);
        $minutes = intval($timeParts[1]);
        $totalMinutes = ($hours * 60) + $minutes;

        // Categorize time based on activity_type
        $activityType = $decodedString['activity_type'] ?? 'Unknown';
		$statusType = $row->status;
		//$statusType = $decodedString['status'] ?? 'Unknown';
        if ($activityType === 'Billable') {
            $totalBillableMinutes += $totalMinutes;
        } elseif ($activityType === 'Non Billable') {
            $totalNonBillableMinutes += $totalMinutes;
        } else {
            $totalInhouseMinutes += $totalMinutes;
        }
    }

    // Convert total minutes back to HH:MM format
    $formattedBillableTime = sprintf('%02d:%02d', floor($totalBillableMinutes / 60), $totalBillableMinutes % 60);
    $formattedNonBillableTime = sprintf('%02d:%02d', floor($totalNonBillableMinutes / 60), $totalNonBillableMinutes % 60);
    $formattedInhouseTime = sprintf('%02d:%02d', floor($totalInhouseMinutes / 60), $totalInhouseMinutes % 60);

    // Return JSON response
    return response()->json([
       'times' => $times,
        'total_billable_hours' => $formattedBillableTime,
        'total_nonbillable_hours' => $formattedNonBillableTime,
        'total_inhouse_hours' => $formattedInhouseTime
    ]);
}

public function GetWorkingHourByProject(Request $request)
{
    // Request se project_id lena
    $projectId = $request->input('project_id');

    // Agar project_id nahi diya gaya, toh error return karein
    if (!$projectId) {
        return response()->json(['error' => 'Project ID is required'], 400);
    }

    // Projects table se project details fetch karna
    $project = DB::table('projects')->where('id', $projectId)->first();

    // Agar project exist nahi karta toh error return karein
    if (!$project) {
        return response()->json(['error' => 'Project not found'], 404);
    }

    // Performa sheets table se sirf "approved" status wale records fetch karein
    $dataQuery = DB::table('performa_sheets')
        ->select('id', 'data', 'status')
        ->where('status', 'approved')
        ->get();

    // Initialize total time counters
    $totalBillableMinutes = 0;
    $totalNonBillableMinutes = 0;
    $totalInhouseMinutes = 0;

    // Loop through each row and process the data
    foreach ($dataQuery as $row) {
        // Decode JSON data
        $decodedString = json_decode($row->data, true);
        if ($decodedString === null) {
            Log::warning("Invalid JSON format in data field for ID: {$row->id}");
            continue;
        }

        // Handle nested JSON issue (if needed)
        if (is_string($decodedString)) {
            $decodedString = json_decode($decodedString, true);
        }

        // Check if required fields exist
        if (!isset($decodedString['time']) || !isset($decodedString['activity_type']) || !isset($decodedString['project_id'])) {
            Log::warning("Missing required fields in data field for ID: {$row->id}");
            continue;
        }

        // Check if project_id matches the given one
        if ($decodedString['project_id'] != $projectId) {
            continue; // Skip if project_id does not match
        }

        // Convert HH:MM time into total minutes
        $timeParts = explode(':', $decodedString['time']);
        if (count($timeParts) !== 2) {
            Log::warning("Invalid time format for ID: {$row->id}, Time: {$decodedString['time']}");
            continue;
        }

        $hours = intval($timeParts[0]);
        $minutes = intval($timeParts[1]);
        $totalMinutes = ($hours * 60) + $minutes;

        // Categorize time based on activity_type
        $activityType = strtolower($decodedString['activity_type']);
        if ($activityType === 'billable') {
            $totalBillableMinutes += $totalMinutes;
        } elseif ($activityType === 'non billable') {
            $totalNonBillableMinutes += $totalMinutes;
        } else {
            $totalInhouseMinutes += $totalMinutes;
        }
    }

    // Convert total minutes back to HH:MM format
    $formattedBillableTime = sprintf('%02d:%02d', floor($totalBillableMinutes / 60), $totalBillableMinutes % 60);
    $formattedNonBillableTime = sprintf('%02d:%02d', floor($totalNonBillableMinutes / 60), $totalNonBillableMinutes % 60);
    $formattedInhouseTime = sprintf('%02d:%02d', floor($totalInhouseMinutes / 60), $totalInhouseMinutes % 60);

    // Total Working Hours (Sum of all types)
    $totalWorkingMinutes = $totalBillableMinutes + $totalNonBillableMinutes + $totalInhouseMinutes;
    $formattedTotalWorkingTime = sprintf('%02d:%02d', floor($totalWorkingMinutes / 60), $totalWorkingMinutes % 60);

    // Return JSON response
    return response()->json([
        'project_id' => $project->id,
        'project_name' => $project->project_name,
        'client_id' => $project->client_id,
        'sales_team_id' => $project->sales_team_id,
        'requirements' => $project->requirements,
        'deadline' => $project->deadline,
        'created_at' => $project->created_at,
        'updated_at' => $project->updated_at,
        'project_total_hours' => $project->total_hours,
        'total_working_hours' => $formattedTotalWorkingTime,
        'total_billable_hours' => $formattedBillableTime ?: '00:00',
        'total_nonbillable_hours' => $formattedNonBillableTime ?: '00:00',
        'total_inhouse_hours' => $formattedInhouseTime ?: '00:00',
    ]);
}

public function GetWeeklyWorkingHourByProject()
{
    // Start aur end date calculate karein
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $endDate = date('Y-m-d');

    // Database se data fetch karein
    $dataQuery = DB::table('performa_sheets')
        ->select('id', 'data', 'status')
        ->where('status', 'approved')
        ->get();

    // Initialize array for processing
    $result = [];

    // Loop through fetched data
    foreach ($dataQuery as $row) {
        // Decode JSON data
        $decodedData = json_decode($row->data, true);
        if ($decodedData === null) {
            Log::warning("Invalid JSON format in data field for ID: {$row->id}");
            continue;
        }

        // Handle double-escaped JSON
        if (is_string($decodedData)) {
            $decodedData = json_decode($decodedData, true);
        }

        // Ensure required fields exist
        if (!isset($decodedData['date'], $decodedData['time'], $decodedData['activity_type'])) {
            Log::warning("Missing required fields in data for ID: {$row->id}");
            continue;
        }

        $recordDate = $decodedData['date'];
        $activityType = $decodedData['activity_type'];
        $timeParts = explode(':', $decodedData['time']);

        // Ensure time format is correct
        if (count($timeParts) !== 2) {
            Log::warning("Invalid time format for ID: {$row->id}, Time: {$decodedData['time']}");
            continue;
        }

        $hours = intval($timeParts[0]);
        $minutes = intval($timeParts[1]);
        $totalMinutes = ($hours * 60) + $minutes;

        // Check if the record is within the date range
        if (strtotime($recordDate) < strtotime($startDate) || strtotime($recordDate) > strtotime($endDate)) {
            continue;
        }

        // Initialize date-wise array
        if (!isset($result[$recordDate])) {
            $result[$recordDate] = [
                'date' => $recordDate,
                'total_hours' => 0,
                'total_billable' => 0,
                'total_non_billable' => 0,
                'total_inhouse' => 0,
            ];
        }

        // Add total time to respective categories
        $result[$recordDate]['total_hours'] += $totalMinutes;

        if ($activityType === 'Billable') {
            $result[$recordDate]['total_billable'] += $totalMinutes;
        } elseif ($activityType === 'Non Billable') {
            $result[$recordDate]['total_non_billable'] += $totalMinutes;
        } else {
            $result[$recordDate]['total_inhouse'] += $totalMinutes;
        }
    }

    // Convert minutes to HH:MM format
    foreach ($result as &$dayData) {
        $dayData['total_hours'] = sprintf('%02d:%02d', floor($dayData['total_hours'] / 60), $dayData['total_hours'] % 60);
        $dayData['total_billable'] = sprintf('%02d:%02d', floor($dayData['total_billable'] / 60), $dayData['total_billable'] % 60);
        $dayData['total_non_billable'] = sprintf('%02d:%02d', floor($dayData['total_non_billable'] / 60), $dayData['total_non_billable'] % 60);
        $dayData['total_inhouse'] = sprintf('%02d:%02d', floor($dayData['total_inhouse'] / 60), $dayData['total_inhouse'] % 60);
    }

    return response()->json(array_values($result));
}

public function GetTotalWorkingHourByEmploye()
{
    $userId = Auth::id(); // Get current logged-in user ID

    // Fetch approved records for the user
    $dataQuery = DB::table('performa_sheets')
        ->select('id', 'data', 'status')
        ->where('status', 'approved')
        ->where('user_id', $userId) // Fetch only logged-in user's data
        ->get();

    // Initialize totals
    $totalHours = 0;
    $totalBillable = 0;
    $totalNonBillable = 0;
    $totalInhouse = 0;

    // Loop through fetched data
    foreach ($dataQuery as $row) {
        // Decode JSON data
        $decodedData = json_decode($row->data, true);
        if ($decodedData === null) {
            Log::warning("Invalid JSON format in data field for ID: {$row->id}");
            continue;
        }

        // Handle double-escaped JSON
        if (is_string($decodedData)) {
            $decodedData = json_decode($decodedData, true);
        }

        // Ensure required fields exist
        if (!isset($decodedData['time'], $decodedData['activity_type'])) {
            Log::warning("Missing required fields in data for ID: {$row->id}");
            continue;
        }

        $activityType = $decodedData['activity_type'];
        $timeParts = explode(':', $decodedData['time']);

        // Ensure time format is correct
        if (count($timeParts) !== 2) {
            Log::warning("Invalid time format for ID: {$row->id}, Time: {$decodedData['time']}");
            continue;
        }

        $hours = intval($timeParts[0]);
        $minutes = intval($timeParts[1]);
        $totalMinutes = ($hours * 60) + $minutes;

        // Add total time to respective categories
        $totalHours += $totalMinutes;

        if ($activityType === 'Billable') {
            $totalBillable += $totalMinutes;
        } elseif ($activityType === 'Non Billable') {
            $totalNonBillable += $totalMinutes;
        } else {
            $totalInhouse += $totalMinutes;
        }
    }

    // Convert minutes to HH:MM format
    return response()->json([
        'user_id' => $userId,
        'total_hours' => sprintf('%02d:%02d', floor($totalHours / 60), $totalHours % 60),
        'total_billable' => sprintf('%02d:%02d', floor($totalBillable / 60), $totalBillable % 60),
        'total_non_billable' => sprintf('%02d:%02d', floor($totalNonBillable / 60), $totalNonBillable % 60),
        'total_inhouse' => sprintf('%02d:%02d', floor($totalInhouse / 60), $totalInhouse % 60),
    ]);
}

public function GetTotalWeeklyWorkingHourByEmploye()
{
    // ✅ Get Current Logged-in User ID
    $userId = Auth::id(); 

    // ✅ Start aur end date calculate karein
    $startDate = date('Y-m-d', strtotime('-6 days')); // 🔹 Ensure last 7 days include today
    $endDate = date('Y-m-d');

    // ✅ 7 days ka empty structure initialize karein
    $dates = [];
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime("$startDate +$i days"));
        $dates[$date] = [
            'date' => $date,
            'total_hours' => '00:00',
            'total_billable' => '00:00',
            'total_non_billable' => '00:00',
            'total_inhouse' => '00:00',
        ];
    }

    // ✅ Database se sirf logged-in user ka approved data fetch karein
    $dataQuery = DB::table('performa_sheets')
        ->select('id', 'data', 'status')
        ->where('status', 'approved')
        ->where('user_id', $userId)
        ->get();

    // ✅ Data process karein
    foreach ($dataQuery as $row) {
        // Decode JSON data
        $decodedData = json_decode($row->data, true);
        if ($decodedData === null) {
            Log::warning("Invalid JSON format in data field for ID: {$row->id}");
            continue;
        }

        // Handle double-escaped JSON
        if (is_string($decodedData)) {
            $decodedData = json_decode($decodedData, true);
        }

        // Ensure required fields exist
        if (!isset($decodedData['date'], $decodedData['time'], $decodedData['activity_type'])) {
            Log::warning("Missing required fields in data for ID: {$row->id}");
            continue;
        }

        $recordDate = $decodedData['date'];
        $activityType = $decodedData['activity_type'];
        $timeParts = explode(':', $decodedData['time']);

        // Ensure time format is correct
        if (count($timeParts) !== 2) {
            Log::warning("Invalid time format for ID: {$row->id}, Time: {$decodedData['time']}");
            continue;
        }

        $hours = intval($timeParts[0]);
        $minutes = intval($timeParts[1]);
        $totalMinutes = ($hours * 60) + $minutes;

        // ✅ Check if the record is within the date range
        if (!isset($dates[$recordDate])) {
            continue;
        }

        // ✅ Convert existing `00:00` to numeric format
        if ($dates[$recordDate]['total_hours'] === '00:00') {
            $dates[$recordDate]['total_hours'] = 0;
            $dates[$recordDate]['total_billable'] = 0;
            $dates[$recordDate]['total_non_billable'] = 0;
            $dates[$recordDate]['total_inhouse'] = 0;
        }

        // ✅ Add total time to respective categories
        $dates[$recordDate]['total_hours'] += $totalMinutes;
        if ($activityType === 'Billable') {
            $dates[$recordDate]['total_billable'] += $totalMinutes;
        } elseif ($activityType === 'Non Billable') {
            $dates[$recordDate]['total_non_billable'] += $totalMinutes;
        } else {
            $dates[$recordDate]['total_inhouse'] += $totalMinutes;
        }
    }

    // ✅ Convert minutes to HH:MM format
    foreach ($dates as &$dayData) {
        if (is_numeric($dayData['total_hours'])) {
            $dayData['total_hours'] = sprintf('%02d:%02d', floor($dayData['total_hours'] / 60), $dayData['total_hours'] % 60);
            $dayData['total_billable'] = sprintf('%02d:%02d', floor($dayData['total_billable'] / 60), $dayData['total_billable'] % 60);
            $dayData['total_non_billable'] = sprintf('%02d:%02d', floor($dayData['total_non_billable'] / 60), $dayData['total_non_billable'] % 60);
            $dayData['total_inhouse'] = sprintf('%02d:%02d', floor($dayData['total_inhouse'] / 60), $dayData['total_inhouse'] % 60);
        }
    }

    // ✅ Convert to JSON response
    return response()->json(array_values($dates));
}

public function GetLastSixMonthsProjectCount()
{
    // ✅ Current date (today's date)
    $today = now();
    $currentDay = $today->day; // Get the day (e.g., 04)

    $data = [];

    // ✅ Loop for last 6 months
    for ($i = 0; $i < 6; $i++) {
        // Calculate Start and End Date for each month
        $startDate = $today->copy()->subMonths($i + 1)->setDay($currentDay);
        $endDate = $today->copy()->subMonths($i)->setDay($currentDay);

        // Handle edge cases where setDay might exceed month's total days
        if ($startDate->day != $currentDay) {
            $startDate = $startDate->copy()->lastOfMonth();
        }
        if ($endDate->day != $currentDay) {
            $endDate = $endDate->copy()->lastOfMonth();
        }

        // ✅ Count projects created within this range
        $totalProjects = DB::table('projects')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // ✅ Store result in array
        $data[] = [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'total_projects' => $totalProjects,
        ];
    }

    // ✅ Return JSON response
    return response()->json($data);
}


}
