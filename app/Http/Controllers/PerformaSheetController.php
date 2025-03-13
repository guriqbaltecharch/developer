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

class PerformaSheetController extends Controller
{
	public function addPerformaSheets(Request $request)
	{
		$user = auth()->user();
		try {
			$validatedData = $request->validate([
				//'user_id' => 'required|exists:users,id',
				'data' => 'required|array',
				'data.*.project_id' => 'required|exists:projects,id',
				'data.*.project_id' => [
			'required',
			Rule::exists('project_user', 'project_id')->where(function ($query) use ($user) {
				$query->where('user_id', $user->id);
			})
		],
				'data.*.date' => 'required|date_format:Y-m-d',
				'data.*.time' => 'required|date_format:H:i',
				'data.*.work_type' => 'required|string|max:255',
				'data.*.activity_type' => 'required|string|max:255',
				'data.*.narration' => 'nullable|string' // ✅ Added narration as a long text field
			]);

			$insertedRecords = [];

			foreach ($validatedData['data'] as $record) {
				$insertedRecords[] = PerformaSheet::create([
					'user_id' => $user->id, // Store user_id
					'data' => json_encode($record) // Store JSON data
				]);
			}

			return response()->json([
				'success' => true,
				'message' => count($insertedRecords) . ' Performa Sheets added successfully',
				'data' => $insertedRecords
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Internal Server Error',
				'error' => $e->getMessage()
			], 500);
		}
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
