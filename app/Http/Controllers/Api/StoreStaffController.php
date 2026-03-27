<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreTherapist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoreStaffController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->provider || !$user->provider->storeProfile) {
            return response()->json(['message' => 'Store profile not found'], 404);
        }

        $staff = StoreTherapist::where('store_profile_id', $user->provider->storeProfile->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($staff);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->provider || !$user->provider->storeProfile) {
            return response()->json(['message' => 'Store profile not found'], 404);
        }

        $staff = StoreTherapist::where('store_profile_id', $user->provider->storeProfile->id)
            ->findOrFail($id);

        return response()->json($staff);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'bio' => 'nullable|string',
            'years_of_experience' => 'required|integer|min:0',
            'photo' => 'nullable|image|max:2048',
        ]);

        $user = $request->user();
        if (!$user->provider || !$user->provider->storeProfile) {
            return response()->json(['message' => 'Store profile not found'], 404);
        }

        $photoUrl = null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('store_therapists', 'public');
            $photoUrl = Storage::url($path);
        }

        $staff = StoreTherapist::create([
            'store_profile_id' => $user->provider->storeProfile->id,
            'name' => $request->name,
            'bio' => $request->bio,
            'years_of_experience' => $request->years_of_experience,
            'profile_photo_url' => $photoUrl,
            'is_active' => true,
        ]);

        event(new \App\Events\StoreStaffUpdated($staff));

        return response()->json($staff, 201);
    }

    public function toggleStatus(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->provider || !$user->provider->storeProfile) {
            return response()->json(['message' => 'Store profile not found'], 404);
        }

        $staff = StoreTherapist::where('store_profile_id', $user->provider->storeProfile->id)
            ->findOrFail($id);

        $staff->is_active = !$staff->is_active;
        $staff->save();

        event(new \App\Events\StoreStaffUpdated($staff));

        return response()->json($staff);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'bio' => 'nullable|string',
            'years_of_experience' => 'sometimes|integer|min:0',
            'photo' => 'nullable|image|max:2048',
        ]);

        $user = $request->user();
        if (!$user->provider || !$user->provider->storeProfile) {
            return response()->json(['message' => 'Store profile not found'], 404);
        }

        $staff = StoreTherapist::where('store_profile_id', $user->provider->storeProfile->id)
            ->findOrFail($id);

        if ($request->has('name'))
            $staff->name = $request->name;
        if ($request->has('bio'))
            $staff->bio = $request->bio;
        if ($request->has('years_of_experience'))
            $staff->years_of_experience = $request->years_of_experience;

        if ($request->hasFile('photo')) {
            // Delete old photo
            if ($staff->profile_photo_url) {
                $oldPath = str_replace('/storage/', '', $staff->profile_photo_url);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('photo')->store('store_therapists', 'public');
            $staff->profile_photo_url = Storage::url($path);
        }

        $staff->save();

        event(new \App\Events\StoreStaffUpdated($staff));

        return response()->json($staff);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->provider || !$user->provider->storeProfile) {
            return response()->json(['message' => 'Store profile not found'], 404);
        }

        $staff = StoreTherapist::where('store_profile_id', $user->provider->storeProfile->id)
            ->findOrFail($id);

        if ($staff->profile_photo_url) {
            $oldPath = str_replace('/storage/', '', $staff->profile_photo_url);
            Storage::disk('public')->delete($oldPath);
        }

        $staffId = $staff->id;
        $storeId = $staff->store_profile_id;
        $staff->delete();

        event(new \App\Events\StoreStaffDeleted($staffId, $storeId));

        return response()->json(['message' => 'Staff member deleted successfully']);
    }
}
