<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PerformaSheetController;


// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::get('/login', function () {
    return response()->json(['message' => 'Please log in to access this resource.'], 401);
})->name('login');
Route::middleware('auth:api')->group(function () {
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/projectManager', [UserController::class, 'projectManger']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    Route::apiResource('/teams', TeamController::class);
    Route::apiResource('/roles', RoleController::class);


    Route::post('/logout', [AuthController::class, 'logout']);

    // Projects API
    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::put('/clients/{id}', [ClientController::class, 'update']);
    Route::delete('/clients/{id}', [ClientController::class, 'destroy']);

    // Projects API
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
    Route::post('/assign-project-manager', [ProjectController::class, 'assignProjectToManager']);
    //Route::post('/assign-project-managers', [ProjectController::class, 'getProjectEmployee']);
    Route::get('/assigned-all-projects', [ProjectController::class, 'getAssignedAllProjects']);
    Route::get('/assigned-projects', [ProjectController::class, 'getAssignedProjects']);
    Route::post('/assign-projectmanager-projectto-employee', [ProjectController::class, 'assignProjectManagerProjectToEmployee']);
    Route::get('/user-projects', [ProjectController::class, 'getUserProjects']);
    Route::get('/get-project-manager-employee', [ProjectController::class, 'getProjectManagerEmployee']);
	
	// Performa API
	Route::post('/add-performa-sheets', [PerformaSheetController::class, 'addPerformaSheets']);
	Route::post('/edit-performa-sheets', [PerformaSheetController::class, 'editPerformaSheets']);
	Route::post('/get-approval-performa-sheets', [PerformaSheetController::class, 'getApprovalPerformaSheets']);
	Route::middleware('auth:api')->group(function () {
    Route::get('/get-performa-sheet', [PerformaSheetController::class, 'getUserPerformaSheets']);
    Route::get('/get-all-performa-sheets', [PerformaSheetController::class, 'getAllPerformaSheets']);
	Route::get('/get-performa-manager-emp', [PerformaSheetController::class, 'getPerformaManagerEmp']);
});

});