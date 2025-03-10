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
        return ApiResponse::success('Clients fetched successfully', ClientResource::collection(Client::all()));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'upwork_id' => 'nullable|string|unique:clients',
            'contact_detail' => 'nullable|string'
        ]);

        $client = Client::create($validatedData);
        return ApiResponse::success('Client created successfully', $client, 201);
    }

    public function update(Request $request, $id)
    {
        $client = Client::find($id);

        if (!$client) {
            return ApiResponse::error('Client not found', [], 404);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'upwork_id' => 'nullable|string|unique:clients,upwork_id,' . $id,
            'contact_detail' => 'nullable|string'
        ]);

        $client->update($validatedData);

        return ApiResponse::success('Client updated successfully', new ClientResource($client));
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
