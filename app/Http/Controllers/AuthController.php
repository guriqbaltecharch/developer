<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Helpers\ApiResponse;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $user = auth()->user()->load('role');

        return ApiResponse::success('Login successful', [
            'token' => $token,
            'user' => $user
        ]);
    }

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return ApiResponse::success('Logout successful');
        } catch (\Exception $e) {
            return ApiResponse::error('Logout failed', ['error' => $e->getMessage()], 500);
        }
    }
}