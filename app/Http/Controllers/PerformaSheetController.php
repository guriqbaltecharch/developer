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
            'data.*.time' => ['required', 'regex:/^\d{2}:\d{2}$/'], // HH:mm format
            'data.*.work_type' => 'required|string|max:255',
            'data.*.activity_type' => 'required|string|max:255',
            'data.*.narration' => 'nullable|string',
            'data.*.project_type' => 'required|string|max:255', // ✅ New field
            'data.*.project_type_status' => 'required|string|max:255', // ✅ New field
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed!',
            'errors' => $e->errors()
        ], 422);
    }

    $insertedRecords = [];
    $projectHours = [];
    $totalHoursPerProject = [];
    $limitExceededProjects = [];
    $projectRecords = [];

    // ✅ Store project data grouped by `project_id`
    foreach ($validatedData['data'] as $record) {
        list($hours, $minutes) = explode(':', $record['time']);
        $timeInHours = (int)$hours + ((int)$minutes / 60);

        if (!isset($projectHours[$record['project_id']])) {
            $projectHours[$record['project_id']] = 0;
            $projectRecords[$record['project_id']] = $record; // ✅ Store correct data for each `project_id`
        }
        $projectHours[$record['project_id']] += $timeInHours;
    }

    // ✅ Process each project separately with correct data
    foreach ($projectHours as $projectId => $newlyInsertedHours) {
        $project = Project::find($projectId);
        $record = $projectRecords[$projectId]; // ✅ Get the correct record for this project_id

        if ($project) {
            $previousTotalHours = $project->total_working_hours;
            $totalHoursLimit = $project->total_hours;
            $finalTotalHours = $previousTotalHours + $newlyInsertedHours;
            $project->total_working_hours = $finalTotalHours;
            $project->save();

            $originalActivityType = $record['activity_type'];
            $message = "";

            if ($originalActivityType == "Billable") {
                $message = "I am Billable";
            } else if ($originalActivityType == "Non Billable") {
                $message = "I am Non Billable";
            } else if ($originalActivityType == "Inhouse") {
                $message = "I am Inhouse";
            }

            // ✅ If "Inhouse" or "Non Billable", add simple row (No extra row)
            if ($originalActivityType == "Inhouse" || $originalActivityType == "Non Billable") {
                $insertedRecords[] = PerformaSheet::create([
                    'user_id' => $user->id,
                    'data' => json_encode([
                        'project_id' => $projectId,
                        'date' => $record['date'],
                        'time' => $record['time'],
                        'work_type' => $record['work_type'],
                        'narration' => $record['narration'],
                        'activity_type' => $originalActivityType,
                        'project_type' => $record['project_type'], // ✅ New field
                        'project_type_status' => $record['project_type_status'], // ✅ New field
                        'message' => "$message - Hours added without limit check"
                    ])
                ]);
            } 
            // ✅ If "Billable", check limits and split if needed
            else {
                $remainingHours = max(0, $totalHoursLimit - $previousTotalHours);
                $extraHours = max(0, $newlyInsertedHours - $remainingHours);

                if ($finalTotalHours > $totalHoursLimit) {
                    if ($remainingHours > 0) {
                        $insertedRecords[] = PerformaSheet::create([
                            'user_id' => $user->id,
                            'data' => json_encode([
                                'project_id' => $projectId,
                                'date' => $record['date'],
                                'time' => sprintf("%02d:00", $remainingHours),
                                'work_type' => $record['work_type'],
                                'narration' => $record['narration'],
                                'activity_type' => "Billable",
                                'project_type' => $record['project_type'], // ✅ New field
                                'project_type_status' => $record['project_type_status'], // ✅ New field
                                'message' => "Billable - Only remaining hours added before limit exceeded"
                            ])
                        ]);
                    }

                    if ($extraHours > 0) {
                        $insertedRecords[] = PerformaSheet::create([
                            'user_id' => $user->id,
                            'data' => json_encode([
                                'project_id' => $projectId,
                                'date' => $record['date'],
                                'time' => sprintf("%02d:00", $extraHours),
                                'work_type' => $record['work_type'],
                                'narration' => $record['narration'],
                                'activity_type' => "Non Billable",
                                'project_type' => $record['project_type'], // ✅ New field
                                'project_type_status' => $record['project_type_status'], // ✅ New field
                                'message' => "Extra hours marked as Non Billable"
                            ])
                        ]);
                    }

                    $limitExceededProjects[$projectId] = [
                        "project_id" => $projectId,
                        "project_name" => $project->project_name,
                        "total_working_hours" => $finalTotalHours,
                        "limit" => $totalHoursLimit,
                        "status" => "limit pending",
                        "exceeded_by" => $extraHours
                    ];
                } else {
                    $insertedRecords[] = PerformaSheet::create([
                        'user_id' => $user->id,
                        'data' => json_encode([
                            'project_id' => $projectId,
                            'date' => $record['date'],
                            'time' => $record['time'],
                            'work_type' => $record['work_type'],
                            'narration' => $record['narration'],
                            'activity_type' => "Billable",
                            'project_type' => $record['project_type'], // ✅ New field
                            'project_type_status' => $record['project_type_status'], // ✅ New field
                            'message' => "Billable - Hours added successfully"
                        ])
                    ]);
                }
            }

            $totalHoursPerProject[$projectId] = [
                "project_id" => $projectId,
                "project_name" => $project->project_name,
                "total_working_hours" => $finalTotalHours,
                "status" => ($finalTotalHours > $totalHoursLimit) ? "limit pending" : "ok"
            ];
        }
    }

    return response()->json([
        'success' => true,
        'inserted_records' => count($insertedRecords) . ' Performa Sheets added successfully',
        'total_hours_per_project' => $totalHoursPerProject,
        'exceeded_projects' => $limitExceededProjects
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
		$validatedData = $request->validate([
			'data' => 'required|array', // ✅ Array of ID-Status pairs
			'data.*.id' => 'required|exists:performa_sheets,id', // ✅ ID must exist
			'data.*.status' => 'required|string|in:approved,rejected' // ✅ Status must be valid
		]);

		$updatedIds = [];
		// Loop through each ID & update individually
		foreach ($validatedData['data'] as $item) {
			PerformaSheet::where('id', $item['id'])->update(['status' => $item['status']]);
			$updatedIds[] = ['id' => $item['id'], 'status' => $item['status']];
		}

		return response()->json([
			'success' => true,
			'message' => "Status updated successfully for " . count($updatedIds) . " records!",
			'updated_data' => $updatedIds
		]);
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
            'data.narration' => 'nullable|string' 
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

        // ✅ Get Old Data & Status
        $oldData = json_decode($performaSheet->data, true);
        $oldStatus = $performaSheet->status; // ✅ Fetch previous status
        $newData = $validatedData['data'];

        // ✅ Check if Any Data is Changed
        $isChanged = $oldData != $newData;

        // ✅ If Data is Changed, Update Status Accordingly
        if ($isChanged) {
            if ($oldStatus === 'Approved' || $oldStatus === 'Rejected' || $oldStatus === 'approved' || $oldStatus === 'rejected') {
                $performaSheet->status = 'Pending'; // ✅ Change only if previous status was Approved/Rejected
            }
            $performaSheet->data = json_encode($newData);
            $performaSheet->save();

            return response()->json([
                'success' => true,
                'message' => 'Performa Sheet updated successfully',
                'status' => $performaSheet->status, // ✅ Return updated status
                'data' => $performaSheet
            ]);
        } else {
            return response()->json([
                'success' => true,
                'message' => 'No changes detected.',
                'status' => $oldStatus, // ✅ Return previous status
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