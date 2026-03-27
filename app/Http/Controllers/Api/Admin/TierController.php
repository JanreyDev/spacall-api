<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tier;
use App\Services\VipService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TierController extends Controller
{
    protected $vipService;

    public function __construct(VipService $vipService)
    {
        $this->vipService = $vipService;
    }

    /**
     * Get all tiers.
     */
    public function index(): JsonResponse
    {
        $tiers = Tier::orderBy('level', 'asc')->get();
        return response()->json([
            'tiers' => $tiers
        ]);
    }

    /**
     * Update tier requirements.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $tier = Tier::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:100',
            'online_minutes_required' => 'required|integer|min:0',
            'extensions_required' => 'required|integer|min:0',
            'bookings_required' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tier->update($request->only([
            'name',
            'online_minutes_required',
            'extensions_required',
            'bookings_required'
        ]));

        // Pro-Tip: Immediate re-evaluation of all therapists
        $this->vipService->reevaluateAll();

        return response()->json([
            'message' => 'Tier requirements updated successfully and therapists re-evaluated.',
            'tier' => $tier
        ]);
    }

    /**
     * Get therapists in a specific tier.
     */
    public function members($id): JsonResponse
    {
        $tier = Tier::findOrFail($id);

        $members = \App\Models\Provider::with([
            'user',
            'therapistProfile',
            'therapistStat',
            'locations' => function ($q) {
                $q->orderBy('recorded_at', 'desc')->take(1);
            }
        ])
            ->where('current_tier_id', $tier->id)
            ->where('type', 'therapist')
            ->get();

        return response()->json([
            'tier' => $tier,
            'members' => $members
        ]);
    }
}
