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
    // Query to get all rows from the performa_sheets table with 'id' and 'data'
    $data = DB::table('performa_sheets')->select('id', 'data')->get();

    // Initialize arrays and total time counters
    $times = [];
    $totalBillableMinutes = 0;
    $totalNonBillableMinutes = 0;
	$totalInhouseMinutes = 0;

    // Loop through each row and process the data
    foreach ($data as $row) {
        // First decode the escaped string
        $decodedString = json_decode($row->data, true);

        // Check if the first decoding was successful
        if ($decodedString === null) {
            Log::warning('Invalid JSON in data field for ID ' . $row->id);
            continue; // Skip this row if JSON is invalid
        }

        // Now decode the JSON data (which might still have escape characters)
        $decodedData = json_decode($decodedString, true);

        // Check if decoding was successful
        if ($decodedData === null) {
            Log::warning('Invalid inner JSON structure for ID ' . $row->id);
            continue; // Skip this row if inner JSON is invalid
        }

        // Debugging: Log the decoded data to check its structure
        Log::info('Decoded Data:', ['data' => $decodedData]);

        // Store all times, regardless of activity_type
        if (isset($decodedData['time'])) {
            $times[] = [
                'id' => $row->id,
                'time' => $decodedData['time'],
                'activity_type' => $decodedData['activity_type'] ?? 'Unknown' // Default to 'Unknown' if not set
            ];

            // Convert HH:MM time into total minutes
            list($hours, $minutes) = explode(':', $decodedData['time']);
            $totalMinutes = ($hours * 60) + $minutes;

            // **Only Add Time if activity_type is "Billable"**
            if (isset($decodedData['activity_type'])) {
                if ($decodedData['activity_type'] === 'Billable') {
                    $totalBillableMinutes += $totalMinutes;
                } else if ($decodedData['activity_type'] === 'Non Billable') {
                    $totalNonBillableMinutes += $totalMinutes;
                }
				else{
                    $totalInhouseMinutes += $totalMinutes;
                }
            }
        } else {
            Log::warning('No time field found for ID ' . $row->id);
        }
    }

    // Convert total minutes back to HH:MM format
    $formattedBillableTime = sprintf('%02d:%02d', floor($totalBillableMinutes / 60), $totalBillableMinutes % 60);
    $formattedNonBillableTime = sprintf('%02d:%02d', floor($totalNonBillableMinutes / 60), $totalNonBillableMinutes % 60);
    $formattedIhouseTime = sprintf('%02d:%02d', floor($totalInhouseMinutes / 60), $totalInhouseMinutes % 60);

    // Return the times along with total working hours
    return response()->json([
        //'times' => $times, // Show all times
        'total_billable_hours' => $formattedBillableTime, // Sum only billable times
        'total_nonbillable_hours' => $formattedNonBillableTime, // Sum only non-billable times
        'total_inhouse_hours' => $formattedIhouseTime // Sum only non-billable times
    ]);
}




}
