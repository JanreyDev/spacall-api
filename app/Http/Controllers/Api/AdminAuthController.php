<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
    /**
     * Admin Login via Email and Password.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            // Check pin_hash as fallback if it's currently used for admin passwords
            if (!$user || !Hash::check($request->password, $user->pin_hash)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }
        }

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            return response()->json(['message' => 'Account is not active.'], 403);
        }

        $token = $user->createToken('admin-dashboard-token', ['role:admin'])->plainTextToken;

        return response()->json([
            'message' => 'Success',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Logout and revoke token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }
}
