<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TagsActivity;
use Illuminate\Support\Facades\DB;

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
		

$tags = DB::table('tagsactivity')->get(); 

return response()->json($tags);
    }
	public function UpdateActivityTag(Request $request, $id)
{
    return response()->json(['message' => 'Received ID:', 'id' => $id]);
}
}
