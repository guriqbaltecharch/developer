<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TagsActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TagActivityController extends Controller
{
     // Store a new tag
    public function AddActivityTag(Request $request) 
	{
		//return response()->json(['message' => 'Test']);
        $request->validate([
            'name' => 'required|string|unique:tagsactivity,name',
        ]);

        $tag = TagsActivity::create(['name' => $request->name]);

        return response()->json(['message' => 'Tag added successfully', 'tag' => $tag]);
    }
	public function GetActivityTag() 
	{
		//return response()->json(['message' => 'Test']);
        $tags = TagsActivity::all();
        return response()->json($tags);
    }
	public function UpdateActivityTag(Request $request, $id)
{
    // Validate request
    $request->validate([
        'name' => 'required|string|unique:tagsactivity,name,' . $id,
    ]);

	return response()->json(['message' => $request]);
    // Find the tag
    $tag = TagsActivity::find($id);

    // Check if tag exists
    if (!$tag) {
        return response()->json(['message' => 'Tag not found'], 404);
    }

    // Update tag
    $tag->update(['name' => $request->name]);

    return response()->json(['message' => 'Tag updated successfully', 'tag' => $tag]);
}

}
