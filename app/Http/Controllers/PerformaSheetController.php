<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\User;
use App\Models\Tasks;
use App\Http\Helpers\ApiResponse;
use App\Http\Resources\ProjectResource;
use App\Models\PerformaSheet;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class PerformaSheetController extends Controller
{

public function addPerformaSheets(Request $request)
{
    $user = auth()->user();

    try {
        $validatedData = $request->validate([
            'data' => 'required|array',
            'data.*.project_id' => [
                'required',
                Rule::exists('project_user', 'project_id')->where(fn($query) => $query->where('user_id', $user->id))
            ],
            'data.*.date' => 'required|date_format:Y-m-d',
            'data.*.time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'data.*.work_type' => 'required|string|max:255',
            'data.*.activity_type' => 'required|string|max:255',
            'data.*.narration' => 'nullable|string',
            'data.*.project_type' => 'required|string|max:255',
            'data.*.project_type_status' => 'required|string|max:255',
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed!',
            'errors' => $e->errors()
        ], 422);
    }

    $inserted = [];

    foreach ($validatedData['data'] as $record) {
        $inserted[] = PerformaSheet::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'data' => json_encode($record)
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => count($inserted) . ' Performa Sheets added successfully',
    ]);
}




	public function getUserPerformaSheets()
	{
		$user = auth()->user(); // Get logged-in user
		// Fetch only logged-in user's sheets
		$sheets = PerformaSheet::with('user:id,name')
					->where('user_id', $user->id) // Filter by logged-in user
					->get();

		$structuredData = [
			'user_id' => $user->id,
			'user_name' => $user->name,
			'sheets' => []
		];

		foreach ($sheets as $sheet) {
			$dataArray = json_decode($sheet->data, true);

			if (!is_array($dataArray)) {
				continue; // Skip if data is not valid JSON
			}

			// Extract project_id
			$projectId = $dataArray['project_id'] ?? null;
			
			// Fetch project details (project_name, client_name, deadline)
			$project = $projectId ? Project::with('client:id,name')->find($projectId) : null;
			$projectName = $project->project_name ?? 'No Project Found';
			$clientName = $project->client->name ?? 'No Client Found';
			$deadline = $project->deadline ?? 'No Deadline Set';

			// Remove user_id and user_name from sheet data (No need to repeat)
			unset($dataArray['user_id'], $dataArray['user_name']);

			// Add project_name, client_name, and deadline to sheet data
			$dataArray['id'] = $sheet->id; // Row ID
			$dataArray['project_name'] = $projectName;
			$dataArray['client_name'] = $clientName;
			$dataArray['deadline'] = $deadline;
			$dataArray['status'] = $sheet->status ?? 'pending';
			$structuredData['sheets'][] = $dataArray;
		}

		// If no work is assigned, add "No Work Assigned" in sheets array
		if (empty($structuredData['sheets'])) {
			$structuredData['sheets'][] = [
				"message" => "No Work Assigned"
			];
		}

		return response()->json([
			'success' => true,
			'message' => 'Performa Sheets fetched successfully',
			'data' => $structuredData
		]);
	}
	
	public function getAllPerformaSheets()
	{
		// Fetch all Performa Sheets with user details
		$sheets = PerformaSheet::with('user:id,name')->get();

		$structuredData = [];

		foreach ($sheets as $sheet) {
			$dataArray = json_decode($sheet->data, true);

			if (!is_array($dataArray)) {
				continue; // Skip if data is not valid JSON
			}

			// Extract project_id
			$projectId = $dataArray['project_id'] ?? null;
			
			// Fetch project details (project_name, client_name, deadline)
			$project = $projectId ? Project::with('client:id,name')->find($projectId) : null;
			$projectName = $project->project_name ?? 'No Project Found';
			$clientName = $project->client->name ?? 'No Client Found';
			$deadline = $project->deadline ?? 'No Deadline Set';

			// Remove user_id and user_name from sheet data (No need to repeat)
			unset($dataArray['user_id'], $dataArray['user_name']);

			// Add project_name, client_name, deadline, and status to sheet data
			$dataArray['project_name'] = $projectName;
			$dataArray['client_name'] = $clientName;
			$dataArray['deadline'] = $deadline;
			$dataArray['status'] = $sheet->status ?? 'pending';
			// Use database ID as serial number
			$dataArray['id'] = $sheet->id; 

			// Group by user ID to avoid duplicate entries
			if (!isset($structuredData[$sheet->user_id])) {
				$structuredData[$sheet->user_id] = [
					'user_id' => $sheet->user_id,
					'user_id' => $sheet->user_id,
					'user_name' => $sheet->user->name,
					'sheets' => []
				];
			}

			$structuredData[$sheet->user_id]['sheets'][] = $dataArray;
		}

		// Convert associative array to indexed array
		$structuredData = array_values($structuredData);

		return response()->json([
			'success' => true,
			'message' => 'All Performa Sheets fetched successfully',
			'data' => $structuredData
		]);
	}
	
	
public function getApprovalPerformaSheets(Request $request)
{
    $request->validate([
        'ids' => 'required|array',
        'status' => 'required|string'
    ]);

    $responses = [];

    foreach ($request->ids as $id) {
        $performa = PerformaSheet::find($id);

        if (!$performa) {
            $responses[] = [
                'id' => $id,
                'success' => false,
                'message' => 'Performa not found'
            ];
            continue;
        }

        $data = json_decode($performa->data, true);
        $projectId = $data['project_id'];
        $activityType = $data['activity_type'];

        list($hours, $minutes) = explode(':', $data['time']);
        $timeInHours = (int)$hours + ((int)$minutes / 60);

        $project = Project::find($projectId);
        if (!$project) {
            $responses[] = [
                'id' => $id,
                'success' => false,
                'message' => 'Project not found'
            ];
            continue;
        }

        // Update total_hours from tasks
        $totalTaskHours = DB::table('tasks')
            ->where('project_id', $projectId)
            ->sum('hours');
        $project->total_hours = $totalTaskHours;

        $totalHoursLimit = $project->total_hours;
        $previousTotalHours = $project->total_working_hours;

        $finalTotalHours = $previousTotalHours + $timeInHours;

        if ($request->status === 'approved') {
            $remainingHours = max(0, $totalHoursLimit - $previousTotalHours);
            $extraHours = max(0, $timeInHours - $remainingHours);

            if ($activityType === "Billable" && $finalTotalHours > $totalHoursLimit) {
                if ($remainingHours > 0) {
                    // Update original performa with billable remaining
                    $data['time'] = sprintf('%02d:%02d', floor($remainingHours), ($remainingHours - floor($remainingHours)) * 60);
                    $data['activity_type'] = "Billable";
                    $data['message'] = "Billable - within limit";
                    $performa->data = json_encode($data);
                }

                if ($extraHours > 0) {
                    // Create Non Billable for extra and mark as approved
                    $newPerforma = new PerformaSheet();
                    $newPerforma->user_id = $performa->user_id;
                    $newPerforma->status = 'approved'; // ✅ Set status here
                    $newPerforma->data = json_encode([
                        'project_id' => $projectId,
                        'date' => $data['date'],
                        'time' => sprintf('%02d:%02d', floor($extraHours), ($extraHours - floor($extraHours)) * 60),
                        'work_type' => $data['work_type'],
                        'narration' => $data['narration'],
                        'activity_type' => "Non Billable",
                        'project_type' => $data['project_type'] ?? '',
                        'project_type_status' => $data['project_type_status'] ?? '',
                        'message' => "Non Billable - Extra hours approved"
                    ]);
                    $newPerforma->save();
                }

                // ✅ Always update total working hours (full time)
                $project->total_working_hours += $timeInHours;
                $project->save();
            } else {
                // No limit issue, simply approve and update working hours
                $project->total_working_hours += $timeInHours;
                $project->save();
            }

            $performa->status = 'approved';
        } else {
            // For rejected or pending status, do not touch hours
            $performa->status = $request->status;
        }

        $performa->save();

        $responses[] = [
            'id' => $id,
            'success' => true,
            'message' => 'Performa updated successfully',
            'final_total_working_hours' => $project->total_working_hours,
            'remaining_hours' => $remainingHours ?? null,
            'extra_hours' => $extraHours ?? null
        ];
    }

    return response()->json([
        'results' => $responses
    ]);
}


public function SinkPerformaAPI(Request $request)
{
    $request->validate([
        'project_id' => 'required|integer',
    ]);

    $projectId = $request->project_id;

    // 1. Get all task statuses
    $statuses = DB::table('tasks')
        ->where('project_id', $projectId)
        ->pluck('status');

    // 2. Check if all tasks are "Completed"
    $allTasksCompleted = !$statuses->contains(function ($status) {
        return strtolower($status) !== 'completed';
    });

    if (!$allTasksCompleted) {
        return response()->json([
            'success' => true,
            'message' => '❌ All tasks are not completed',
            'all_completed' => false,
            'remaining_hours' => 0
        ]);
    }

    // 3. Get project details
    $project = DB::table('projects')->where('id', $projectId)->first();

    if (!$project) {
        return response()->json([
            'success' => false,
            'message' => 'Project not found'
        ], 404);
    }

    $totalHours = (float) $project->total_hours;
    $workingHours = (float) $project->total_working_hours;
    $remainingHours = max(0, $totalHours - $workingHours);

    // 4. Get all Non Billable entries for this project from performa_sheets
    $entries = PerformaSheet::where('status', 'approved')->get()->filter(function ($entry) use ($projectId) {
        $data = json_decode($entry->data, true);
        return isset($data['project_id'], $data['activity_type']) &&
            $data['project_id'] == $projectId &&
            $data['activity_type'] == 'Non Billable';
    });

    $converted = [];
    $remaining = $remainingHours;

    foreach ($entries as $entry) {
        $data = json_decode($entry->data, true);
        $entryHours = timeToFloat($data['time']); // helper to convert HH:MM to float
        if ($remaining <= 0) break;

        if ($entryHours <= $remaining) {
            // Fully convert to Billable
            $data['activity_type'] = 'Billable';
            $data['message'] = 'Converted from Non Billable to Billable via Sync';
            $entry->data = json_encode($data);
            $entry->save();

            $converted[] = $entry;
            $remaining -= $entryHours;
            $workingHours += $entryHours;
        } else {
            // Partially convert: update existing with Billable, create new with leftover Non Billable
            $billableTime = floatToTime($remaining);
            $nonBillableTime = floatToTime($entryHours - $remaining);

            // Update current entry
            $data['time'] = $billableTime;
            $data['activity_type'] = 'Billable';
            $data['message'] = 'Partially converted to Billable via Sync';
            $entry->data = json_encode($data);
            $entry->save();

            $workingHours += $remaining;

            // Create new Non Billable entry with leftover
            $newData = $data;
            $newData['activity_type'] = 'Non Billable';
            $newData['time'] = $nonBillableTime;
            $newData['message'] = 'Remaining Non Billable after partial conversion';

            $newEntry = PerformaSheet::create([
                'user_id' => $entry->user_id,
                'data' => json_encode($newData),
                'status' => 'approved', // ✅ Your requested change
            ]);

            $converted[] = $entry;
            $converted[] = $newEntry;

            $remaining = 0;
        }
    }

    // Update project total_working_hours
    DB::table('projects')->where('id', $projectId)->update([
        'total_working_hours' => $workingHours
    ]);

    return response()->json([
        'success' => true,
        'message' => '✅ Non Billable entries converted based on remaining hours',
        'converted' => $converted,
        'updated_total_working_hours' => $workingHours,
        'remaining_after_conversion' => max(0, $totalHours - $workingHours),
    ]);
}


// Helper: convert "HH:MM" to float (like "01:30" => 1.5)
private function convertTimeToFloat($time)
{
    [$hours, $minutes] = explode(':', $time);
    return (int)$hours + ((int)$minutes / 60);
}

// Helper: convert float back to "HH:MM"
private function convertFloatToTime($float)
{
    $hours = floor($float);
    $minutes = round(($float - $hours) * 60);
    return str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0', STR_PAD_LEFT);
}






	
	public function getPerformaManagerEmp()
	{
		$projectManager = auth()->user(); // Get logged-in project manager
		$teamId = $projectManager->team_id; // ✅ Fetch Project Manager's team_id
		// Fetch only users from the same team
		$sheets = PerformaSheet::with(['user:id,name,team_id'])
					->whereHas('user', function ($query) use ($teamId) {
						$query->where('team_id', $teamId);
					})
					->get();

		$structuredData = [];
		foreach ($sheets as $sheet) {
        $dataArray = json_decode($sheet->data, true);

        if (!is_array($dataArray)) {
            continue; // Skip if data is not valid JSON
        }

        // Extract project_id & date
        $projectId = $dataArray['project_id'] ?? null;
        $date = $dataArray['date'] ?? '0000-00-00'; // Default value to avoid errors

        // Fetch project details (project_name, client_name, deadline)
        $project = $projectId ? Project::with('client:id,name')->find($projectId) : null;
        $projectName = $project->project_name ?? 'No Project Found';
        $clientName = $project->client->name ?? 'No Client Found';
        $deadline = $project->deadline ?? 'No Deadline Set';

        // Add project_name, client_name, deadline, and status to sheet data
        $dataArray['project_name'] = $projectName;
        $dataArray['client_name'] = $clientName;
        $dataArray['deadline'] = $deadline;
        $dataArray['status'] = $sheet->status ?? 'pending';
        $dataArray['user_id'] = $sheet->user->id;
        $dataArray['user_name'] = $sheet->user->name;
        $dataArray['performa_sheet_id'] = $sheet->id;

        // Store in structuredData array
        $structuredData[] = $dataArray;
		}
		// ✅ Sort all records globally by `date` (Latest first)
		$structuredData = collect($structuredData)->sortByDesc('date')->values()->toArray();
		return response()->json([
        'success' => true,
        'message' => 'Performa Sheets fetched successfully',
        'project_manager_id' => $projectManager->id,
        'team_id' => $teamId, // ✅ Include team_id in response
        'data' => $structuredData
		]);
	}

	public function editPerformaSheets(Request $request)
{
	 $user = auth()->user();

    try {
        // ✅ Validate the request including tags_activitys
        $validatedData = $request->validate([
            'id' => 'required|exists:performa_sheets,id',
            'data' => 'required|array',
            'data.project_id' => [
                'required',
                Rule::exists('project_user', 'project_id')->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
            ],
            'data.date' => 'required|date_format:Y-m-d',
            'data.time' => 'required|date_format:H:i',
            'data.work_type' => 'required|string|max:255',
            'data.activity_type' => 'required|string|max:255',
            'data.narration' => 'nullable|string',
            'data.project_type' => 'required|string|max:255',
            'data.project_type_status' => 'required|string|max:255',
            'data.tags_activitys' => 'nullable|array', // ✅ Add validation for tags_activitys
            'data.tags_activitys.*' => 'integer|exists:tagsactivity,id',
        ]);

        // ✅ Find Performa Sheet
        $performaSheet = PerformaSheet::where('id', $validatedData['id'])
                                      ->where('user_id', $user->id)
                                      ->first();

        if (!$performaSheet) {
            return response()->json([
                'success' => false,
                'message' => 'Performa Sheet not found or you do not have permission to edit it.'
            ], 404);
        }

        // ✅ Get Old and New Data
        $oldData = json_decode($performaSheet->data, true);
        $oldStatus = $performaSheet->status;
        $newData = $validatedData['data'];

        // ✅ Check if any data is changed
        $isChanged = $oldData != $newData;

        if ($isChanged) {
            if (in_array(strtolower($oldStatus), ['approved', 'rejected'])) {
                $performaSheet->status = 'Pending';
            }

            $performaSheet->data = json_encode($newData);
            $performaSheet->save();

            return response()->json([
                'success' => true,
                'message' => 'Performa Sheet updated successfully',
                'status' => $performaSheet->status,
                'data' => $performaSheet
            ]);
        } else {
            return response()->json([
                'success' => true,
                'message' => 'No changes detected.',
                'status' => $oldStatus,
                'data' => $performaSheet
            ]);
        }

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Internal Server Error',
            'error' => $e->getMessage()
        ], 500);
    }
}




		

}