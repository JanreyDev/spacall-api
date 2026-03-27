<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $latitude = $request->query('latitude');
        $longitude = $request->query('longitude');

        $query = StoreProfile::with(['provider.user', 'provider.services', 'staff' => function($q) {
            $q->where('is_active', true);
        }]);

        if ($latitude && $longitude) {
            // Haversine formula for distance
            $query->selectRaw("*, (
                6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )
            ) AS distance", [$latitude, $longitude, $latitude])
            ->orderBy('distance');
        } else {
            $query->inRandomOrder();
        }

        $stores = $query->paginate(10);

        return response()->json($stores);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $store = StoreProfile::with(['provider.user', 'provider.services', 'staff' => function($q) {
            $q->where('is_active', true);
        }])->findOrFail($id);
        
        // Calculate distance if coordinates provided
        $latitude = request()->query('latitude');
        $longitude = request()->query('longitude');
        
        if ($latitude && $longitude) {
            $lat1 = $latitude;
            $lon1 = $longitude;
            $lat2 = $store->latitude;
            $lon2 = $store->longitude;
            
            $theta = $lon1 - $lon2;
            $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
            $dist = acos($dist);
            $dist = rad2deg($dist);
            $miles = $dist * 60 * 1.1515;
            $store->distance = $miles * 1.609344; // Convert to KM
        }

        return response()->json($store);
    }
}
