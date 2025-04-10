<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TagsActivity;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\ApiResponse;

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
	public function AddActivityTags(Request $request) 
	{
		return response()->json(['message' => 'Test']);
        
    }
	public function GetActivityTag() 
	{
		

$tags = DB::table('tagsactivity')->get(); 

return response()->json($tags);
    }


public function updateActivityTag(Request $request, $id)
{
   
    // Validate request
    $request->validate([
        'name' => 'required|string|max:255'
    ]);

    // Check if the tag exists
    $tag = DB::table('tagsactivity')->where('id', $id)->first();

    if (!$tag) {
        return response()->json(['message' => 'Tag not found'], 404);
    }

    // Update the tag name
    DB::table('tagsactivity')->where('id', $id)->update([
        'name' => $request->name,
        'updated_at' => now() // If timestamps are enabled
    ]);

    // Fetch updated tag
    $updatedTag = DB::table('tagsactivity')->where('id', $id)->first();

    return response()->json([
        'message' => 'Tag updated successfully',
        'tag' => $updatedTag
    ]);
}

public function destroy($id)
{
    $tag = TagsActivity::find($id);
    if (!$tag) {
        return ApiResponse::error('Tag not found', [], 404);
    }
	$tag->delete();
    return ApiResponse::success('Tag deleted successfully');
}


}
