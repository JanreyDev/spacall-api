<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminTherapistController extends Controller
{
    /**
     * Display a listing of all therapists (admin view).
     */
    public function index(Request $request): JsonResponse
    {
        // Admin can see all therapists, regardless of status
        $query = Provider::with([
            'user',
            'therapistProfile',
            'locations' => function ($q) {
                $q->orderBy('recorded_at', 'desc')->take(1);
            }
        ])
            ->where('type', 'therapist');

        // Optional filtering
        if ($request->has('verification_status')) {
            $query->where('verification_status', $request->verification_status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        $therapists = $query->orderBy('created_at', 'desc')->get();

        $now = \Carbon\Carbon::now();
        $mappedTherapists = $therapists->map(function ($therapist) use ($now) {
            $lastLocation = $therapist->locations->first();
            $isRecentlyActive = $lastLocation && \Carbon\Carbon::parse($lastLocation->recorded_at)->diffInMinutes($now) <= 30;

            // Override is_available based on heartbeat
            $therapist->is_available = $therapist->is_available && $isRecentlyActive;
            return $therapist;
        });

        return response()->json([
            'therapists' => [
                'data' => $mappedTherapists
            ]
        ]);
    }

    /**
     * Display the specified therapist.
     */
    public function show(int $id): JsonResponse
    {
        $therapist = Provider::with([
            'user',
            'therapistProfile',
            'therapistStat',
            'currentTier',
            'locations' => function ($q) {
                $q->latest()->take(10);
            },
            'documents',
            'reviews.user'
        ])
            ->findOrFail($id);

        // Calculate next tier progress
        $currentLevel = $therapist->currentTier ? $therapist->currentTier->level : 0;
        $nextTier = \App\Models\Tier::where('level', '>', $currentLevel)
            ->orderBy('level', 'asc')
            ->first();

        $tierProgress = null;
        if ($nextTier) {
            $stats = $therapist->therapistStat;
            $onlineMinutes = $stats ? $stats->total_online_minutes : 0;
            $extensions = $stats ? $stats->total_extensions : 0;
            $bookings = $stats ? $stats->total_bookings : 0;

            $tierProgress = [
                'next_tier_name' => $nextTier->name,
                'next_tier_level' => $nextTier->level,
                'requirements' => [
                    'online_minutes' => [
                        'required' => $nextTier->online_minutes_required,
                        'current' => $onlineMinutes,
                        'remaining' => max(0, $nextTier->online_minutes_required - $onlineMinutes)
                    ],
                    'extensions' => [
                        'required' => $nextTier->extensions_required,
                        'current' => $extensions,
                        'remaining' => max(0, $nextTier->extensions_required - $extensions)
                    ],
                    'bookings' => [
                        'required' => $nextTier->bookings_required,
                        'current' => $bookings,
                        'remaining' => max(0, $nextTier->bookings_required - $bookings)
                    ]
                ]
            ];
        }

        return response()->json([
            'therapist' => $therapist,
            'tier_progress' => $tierProgress
        ]);
    }

    /**
     * Approve VIP application.
     */
    public function approveVip(int $id): JsonResponse
    {
        $provider = Provider::with('user', 'therapistProfile')->findOrFail($id);

        if ($provider->therapistProfile) {
            $provider->therapistProfile->update(['vip_status' => 'approved']);
            $provider->user->update(['customer_tier' => 'vip']);
        }

        return response()->json([
            'message' => 'VIP application approved successfully.',
            'therapist' => $provider
        ]);
    }

    /**
     * Reject VIP application.
     */
    public function rejectVip(int $id): JsonResponse
    {
        $provider = Provider::with('therapistProfile')->findOrFail($id);

        if ($provider->therapistProfile) {
            $provider->therapistProfile->update(['vip_status' => 'rejected']);
        }

        return response()->json([
            'message' => 'VIP application rejected.',
            'therapist' => $provider
        ]);
    }
}
