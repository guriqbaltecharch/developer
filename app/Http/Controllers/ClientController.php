<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use App\Http\Helpers\ApiResponse;
use App\Http\Resources\ClientResource;

class ClientController extends Controller
{
   public function index()
	{
		$clients = Client::select('id', 'name', 'client_type', 'contact_detail', 'hire_on_id', 'company_name', 'company_address', 'created_at', 'updated_at')->get();
		return ApiResponse::success('Clients fetched successfully', ClientResource::collection($clients));
	}

    public function store(Request $request)
    {
          // ✅ Base validation (always required)
		$rules = [
			'client_type' => 'required|string|max:255',
			'name' => 'required|string|max:255',
			'contact_detail' => 'nullable|string|max:255'
		];

		// ✅ Conditional validation based on `client_type`
		if ($request->client_type === "Hired on Upwork") {
			$rules['hire_on_id'] = 'nullable|string|max:255'; // ✅ Required for Upwork clients
		} else {
			$rules['company_name'] = 'nullable|string|max:255';  // ✅ Required for non-Upwork clients
			$rules['company_address'] = 'nullable|string|max:255';
		}

	// ✅ Apply validation rules
	$validatedData = $request->validate($rules);

	// ✅ Store the client
	$client = Client::create($validatedData);

	return response()->json([
		'success' => true,
		'message' => 'Client created successfully',
		'data' => $client
	]);
}

    public function update(Request $request, $id)
{
    $client = Client::find($id);

    if (!$client) {
        return response()->json([
            'success' => false,
            'message' => 'Client not found'
        ], 404);
    }

    // ✅ Base validation
    $rules = [
        'client_type' => 'required|string|max:255',
        'name' => 'required|string|max:255',
        'contact_detail' => 'nullable|string|max:255'
    ];

    // ✅ Conditional validation based on `client_type`
    if ($request->client_type === "Hired on Upwork") {
        $rules['hire_on_id'] = 'nullable|string|max:255';
    } else {
        $rules['company_name'] = 'nullable|string|max:255';
        $rules['company_address'] = 'nullable|string|max:255';
    }

    // ✅ Apply validation rules
    $validatedData = $request->validate($rules);

    // ✅ Update the client
    $client->update($validatedData);

    return response()->json([
        'success' => true,
        'message' => 'Client updated successfully',
        'data' => $client
    ]);
}


    public function destroy($id)
    {
        $client = Client::find($id);

        if (!$client) {
            return ApiResponse::error('Client not found', [], 404);
        }

        $client->delete();
        return ApiResponse::success('Client deleted successfully');
    }

}
