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


class GraphController extends Controller
{
  

public function GraphTotalWorkingHour(Request $request)
{
    // Request se start aur end date lena (Format: YYYY-MM-DD)
    $startDate = $request->input('start_date');
    $endDate = $request->input('end_date');

    // Query ko filter karna based on date range
    $dataQuery = DB::table('performa_sheets')->select('id', 'data');
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
            'date' => $decodedString['date']
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
       // 'times' => $times,
        'total_billable_hours' => $formattedBillableTime,
        'total_nonbillable_hours' => $formattedNonBillableTime,
        'total_inhouse_hours' => $formattedInhouseTime
    ]);
}




}
