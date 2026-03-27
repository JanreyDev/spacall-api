<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;

class ClientController extends Controller
{
    /**
     * Subscribe to VIP Membership.
     */
    public function subscribeVip(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => 'required|string',
        ]);

        $user = $request->user();

        if ($user->role !== 'client') {
            return response()->json(['message' => 'Only clients can subscribe to VIP.'], 403);
        }

        if (!\Illuminate\Support\Facades\Hash::check($request->pin, $user->pin_hash)) {
            return response()->json(['message' => 'Invalid PIN'], 403);
        }

        // In a real app, verify wallet balance or process payment here.
        // For now, we simply upgrade the tier.

        $user->update([
            'customer_tier' => User::TIER_VIP
        ]);

        return response()->json([
            'message' => 'Successfully subscribed to VIP membership.',
            'user' => $user
        ]);
    }

    /**
     * Verify User PIN.
     */
    public function verifyPin(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => 'required|string',
        ]);

        $user = $request->user();

        if (!\Illuminate\Support\Facades\Hash::check($request->pin, $user->pin_hash)) {
            return response()->json(['message' => 'Invalid PIN'], 403);
        }

        return response()->json([
            'message' => 'PIN verified successfully.'
        ]);
    }
}
