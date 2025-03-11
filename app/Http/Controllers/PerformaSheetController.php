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

	
    /*public function getPerformaSheet()
	{
		$user = auth()->user();
		//return response()->json(['message' => 'Test']);
	}*/
}
