<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminClientController extends Controller
{
    /**
     * Display a listing of all clients (admin view).
     */
    public function index(Request $request): JsonResponse
    {
        // Fetch users who are clients
        // Based on tinker output, the role is 'client'
        $query = User::where('role', 'client');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('mobile_number', 'like', "%{$search}%");
            });
        }

        // Include relationships if needed for stats (e.g., bookings count)
        // Assuming relationship name is 'bookings' (User hasMany Bookings likely)
        // If not defined in User.php yet, we can skip or add 'withCount'
        // based on User.php viewing earlier, likely 'bookings' or similar exists or needs adding
        // Let's just return basic info first.
        
        $clients = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'clients' => $clients
        ]);
    }
}
