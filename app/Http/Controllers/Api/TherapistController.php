<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderLocation;
use App\Models\Booking;
use App\Models\BookingLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\VipService;
use App\Models\StoreProfile;
use App\Models\User;

class TherapistController extends Controller
{
    protected $vipService;

    public function __construct(VipService $vipService)
    {
        $this->vipService = $vipService;
    }
    /**
     * Display a listing of all verified therapists.
     */
    public function index(Request $request): JsonResponse
    {
        $lat = $request->query('latitude');
        $lng = $request->query('longitude');
        $radius = $request->query('radius', 30); // Standardize to 30km filter

        $includeOffline = $request->query('include_offline', false);

        $query = Provider::with([
            'user',
            'therapistProfile',
            'currentTier',
            'locations' => function ($q) {
                $q->latest('recorded_at');
            }
        ])
            ->where('providers.type', 'therapist')
            ->where('providers.verification_status', 'verified')
            ->where('providers.is_active', true);

        if (!$includeOffline) {
            $query->whereHas('user', function ($q) {
                $q->where('wallet_balance', '>=', 1000);
            });
            $query->where('providers.is_available', true);
        }

        if ($lat && $lng) {
            $lat = (double) $lat;
            $lng = (double) $lng;

            $query->select('providers.*')
                ->selectRaw(
                    "(6371 * acos(LEAST(1.0, GREATEST(-1.0, cos(radians(?)) * cos(radians(provider_locations.latitude)) * cos(radians(provider_locations.longitude) - radians(?)) + sin(radians(?)) * sin(radians(provider_locations.latitude)))))) AS distance",
                    [$lat, $lng, $lat]
                )
                ->leftJoin('provider_locations', 'providers.id', '=', 'provider_locations.provider_id');

            if (!$includeOffline) {
                $query->where('provider_locations.is_online', true)
                    ->where('provider_locations.recorded_at', '>=', now()->subMinutes(1))
                    ->whereNotNull('provider_locations.latitude')
                    ->whereNotNull('provider_locations.longitude')
                    ->whereRaw(
                        "(6371 * acos(LEAST(1.0, GREATEST(-1.0, cos(radians(?)) * cos(radians(provider_locations.latitude)) * cos(radians(provider_locations.longitude) - radians(?)) + sin(radians(?)) * sin(radians(provider_locations.latitude)))))) <= ?",
                        [$lat, $lng, $lat, $radius]
                    );
            } else {
                // For includeOffline, still sort by distance but allow those without location or far away if VIP
                $query->where(function ($q) use ($lat, $lng, $radius) {
                    $q->whereRaw(
                        "(6371 * acos(LEAST(1.0, GREATEST(-1.0, cos(radians(?)) * cos(radians(provider_locations.latitude)) * cos(radians(provider_locations.longitude) - radians(?)) + sin(radians(?)) * sin(radians(provider_locations.latitude)))))) <= ?",
                        [$lat, $lng, $lat, $radius]
                    )
                        ->orWhereNull('provider_locations.latitude')
                        ->orWhere('providers.current_tier_id', '!=', null)
                        ->orWhereHas('user', function ($sq) {
                            $sq->where('customer_tier', 'vip');
                        });
                });
            }
            $query->orderBy('distance', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $therapists = $query->get();

        return response()->json([
            'therapists' => $therapists
        ]);
    }

    /**
     * Display the specified therapist.
     */
    public function show(string $uuid): JsonResponse
    {
        $therapist = Provider::with(['user', 'therapistProfile', 'services'])
            ->where('uuid', $uuid)
            ->where('type', 'therapist')
            ->whereIn('verification_status', ['verified', 'pending'])
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'therapist' => $therapist
        ]);
    }

    /**
     * Get the authenticated therapist's profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        // Find the provider profile of type therapist
        $provider = $user->providers()
            ->with(['therapistProfile', 'currentTier', 'therapistStat', 'storeProfile'])
            ->where('type', 'therapist')
            ->first();

        // If not therapist but store tier, find store provider
        if (!$provider && $user->customer_tier === 'store') {
            $provider = $user->providers()
                ->with(['storeProfile'])
                ->where('type', 'store')
                ->first();
        }

        // Fallback: If they have ANY provider, fetch it so the app doesn't crash with 404
        if (!$provider) {
            $provider = $user->providers()
                ->with(['therapistProfile', 'storeProfile', 'currentTier', 'therapistStat'])
                ->first();
        }

        if (!$provider) {
            return response()->json([
                'message' => 'Therapist profile not found for this user.'
            ], 404);
        }

        // --- ENFORCE MAINTENANCE BALANCE OR ACTIVE BOOKING ON FETCH ---
        $hasActiveBooking = \App\Models\Booking::where('provider_id', $provider->id)
            ->whereIn('status', ['accepted', 'en_route', 'arrived', 'in_progress'])
            ->exists();

        if (($user->wallet_balance < 1000 || $hasActiveBooking) && $provider->is_available) {
            $provider->update(['is_available' => false]);

            // Sync location status to offline
            \App\Models\ProviderLocation::where('provider_id', $provider->id)
                ->where('is_online', true)
                ->update(['is_online' => false]);

            $provider->refresh();
        }

        return response()->json([
            'user' => $user,
            'provider' => $provider
        ]);
    }

    /**
     * Update the store profile for the authenticated store owner.
     */
    public function updateStoreProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->customer_tier !== 'store') {
            return response()->json(['message' => 'This action is only for store accounts.'], 403);
        }

        // Check for ANY existing provider for this user (user_id is unique)
        $provider = $user->providers()->first();

        if (!$provider) {
            try {
                // Create provider if missing
                $provider = Provider::create([
                    'user_id' => $user->id,
                    'type' => 'store',
                    'verification_status' => 'verified',
                    'is_active' => true,
                    'is_available' => true,
                    'is_accepting_bookings' => true,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Failed to create business provider: ' . $e->getMessage()
                ], 500);
            }
        } else if ($provider->type !== 'store') {
            // Update existing provider type if it was something else
            $provider->update(['type' => 'store']);
        }

        \Log::info('Store Profile update request:', $request->all());
        if ($request->hasFile('photo')) {
            \Log::info('Photo file detected: ' . $request->file('photo')->getClientOriginalName());
        }

        $validator = Validator::make($request->all(), [
            'store_name' => 'required|string|max:150',
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'description' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed: ' . implode(', ', $validator->errors()->all()),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = [
                'store_name' => $request->store_name,
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'description' => $request->description,
                'city' => $request->city ?? 'Tarlac City',
                'province' => $request->province ?? 'Tarlac',
            ];

            if ($request->hasFile('photo')) {
                if (!\Storage::disk('public')->exists('store-photos')) {
                    \Storage::disk('public')->makeDirectory('store-photos');
                }
                $path = $request->file('photo')->store('store-photos', 'public');
                $fullUrl = env('APP_URL', 'https://api.spacall.ph') . '/storage/' . $path;
                $data['photos'] = [$fullUrl]; // Currently just one thumbnail
            }

            $storeProfile = StoreProfile::updateOrCreate(
                ['provider_id' => $provider->id],
                $data
            );

            return response()->json([
                'message' => 'Store profile updated successfully',
                'store_profile' => $storeProfile
            ]);
        } catch (\Exception $e) {
            \Log::error('Store Profile Update Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update store profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update therapist's current location.
     */
    public function updateLocation(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $provider = $user->providers()->where('type', 'therapist')->first()
                ?? $user->providers()->where('type', 'store')->first()
                ?? $user->providers()->first();

            if (!$provider && $user->role === 'therapist') {
                $provider = Provider::create([
                    'user_id' => $user->id,
                    'type' => 'therapist',
                    'verification_status' => 'verified', // Auto-verify for dev
                    'is_active' => true,
                    'is_available' => true,
                    'is_accepting_bookings' => true,
                ]);
            }

            if (!$provider) {
                return response()->json([
                    'message' => 'Therapist profile not found. If you are a client, please register as a therapist first.',
                    'role' => $user->role
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'is_online' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $lat = $request->latitude;
            $lng = $request->longitude;

            // Logic Change: If Store Therapist, override with Store Location if available
            if ($user->customer_tier === 'store' && $provider->storeProfile) {
                $lat = $provider->storeProfile->latitude ?? $lat;
                $lng = $provider->storeProfile->longitude ?? $lng;
            }

            // Check for minimum wallet balance if trying to go online (required for all tiers)
            $isOnlineTransition = $request->is_online ?? true;

            // Force offline if there is an active booking
            $hasActiveBooking = \App\Models\Booking::where('provider_id', $provider->id)
                ->whereIn('status', ['accepted', 'en_route', 'arrived', 'in_progress'])
                ->exists();

            if ($isOnlineTransition && $hasActiveBooking) {
                $provider->update(['is_available' => false]);
                ProviderLocation::where('provider_id', $provider->id)->update(['is_online' => false]);

                return response()->json([
                    'message' => 'You cannot be online while you have an active booking.',
                    'wallet_balance' => (float) $user->wallet_balance,
                    'is_online' => false
                ], 422);
            }

            if ($isOnlineTransition && $user->wallet_balance < 1000) {
                // Ensure profile is marked as unavailable if balance is below minimum maintaining balance
                $provider->update(['is_available' => false]);
                ProviderLocation::where('provider_id', $provider->id)->update(['is_online' => false]);

                return response()->json([
                    'message' => 'Insufficient Wallet Balance. A minimum maintaining balance of ₱1,000.00 is required to activate your online status and accept new bookings. Please top up your wallet to proceed.',
                    'wallet_balance' => (float) $user->wallet_balance,
                    'is_online' => false
                ], 422);
            }

            $location = ProviderLocation::updateOrCreate(
                ['provider_id' => $provider->id],
                [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'is_online' => $isOnlineTransition,
                    'recorded_at' => now(),
                ]
            );

            // Sync Provider availability
            $provider->update(['is_available' => $isOnlineTransition]);

            // BROADCAST: Instant availability update for all clients
            try {
                broadcast(new \App\Events\TherapistAvailabilityChanged(
                    $provider->id,
                    (bool) $isOnlineTransition
                ))->toOthers();
            } catch (\Exception $e) {
                \Log::error("Failed to broadcast availability change: " . $e->getMessage());
            }

            // VIP SYSTEM: Track online minutes
            try {
                if ($isOnlineTransition) {
                    $this->vipService->startOnlineSession($provider);
                } else {
                    $this->vipService->updateOnlineMinutes($provider);
                }
            } catch (\Exception $e) {
                \Log::error("VIP Service Error in updateLocation: " . $e->getMessage());
                // Don't fail the whole request for stats tracking errors
            }

            // Also update therapistProfile base location for static queries if needed
            if ($provider->therapistProfile()->exists()) {
                $provider->therapistProfile()->update([
                    'base_location_latitude' => $request->latitude,
                    'base_location_longitude' => $request->longitude,
                ]);
            }

            // BROADCAST: If therapist has an active booking, broadcast location to the customer
            try {
                $activeBooking = \App\Models\Booking::where('provider_id', $provider->id)
                    ->whereIn('status', ['accepted', 'en_route', 'arrived', 'in_progress'])
                    ->first();

                if ($activeBooking) {
                    broadcast(new \App\Events\LocationUpdated(
                        $activeBooking->id,
                        (float) $request->latitude,
                        (float) $request->longitude
                    ))->toOthers();
                }
            } catch (\Exception $e) {
                \Log::error("Real-time location broadcast failed: " . $e->getMessage());
            }

            return response()->json([
                'message' => 'Location updated successfully',
                'location' => $location
            ]);
        } catch (\Exception $e) {
            \Log::error("Fatal error in updateLocation: " . $e->getMessage());
            return response()->json([
                'message' => 'Server error occurred while updating location.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bookings awaiting assignment near the therapist.
     */
    public function nearbyBookings(Request $request): JsonResponse
    {
        $user = $request->user();

        // --- NEW: Professional Wallet Balance Check ---
        if ($user->wallet_balance < 1000) {
            return response()->json([
                'message' => 'Insufficient Wallet Balance. A minimum maintaining balance of ₱1,000.00 is required to access the booking system. Please top up your wallet to proceed.',
                'wallet_balance' => (float) $user->wallet_balance,
                'is_online' => false
            ], 422);
        }
        $provider = $user->providers()->whereIn('type', ['therapist', 'store'])->first()
            ?? $user->providers()->first();

        if (!$provider) {
            return response()->json(['message' => 'Therapist profile not found'], 404);
        }

        // Get therapist's last recorded location or store's fixed location
        $location = $provider->locations()->latest()->first();

        // Logical fix: If store, prioritize store profile coordinates
        $storeProfile = $provider->storeProfile;
        $fallbackLat = $storeProfile?->latitude ?? $location?->latitude ?? $provider->therapistProfile?->base_location_latitude ?? 14.5995;
        $fallbackLng = $storeProfile?->longitude ?? $location?->longitude ?? $provider->therapistProfile?->base_location_longitude ?? 120.9842;

        // Use query param, then fallback
        $lat = $request->query('latitude', $fallbackLat);
        $lng = $request->query('longitude', $fallbackLng);
        $radius = $request->query('radius', 50); // Default to 50km

        if (!$lat || !$lng) {
            return response()->json(['message' => 'Location not known. Please update location first.'], 400);
        }

        // Find bookings with status 'awaiting_assignment' near the therapist
        // Filter by assignment_type = 'browsable' or direct bookings if we want
        $bookings = Booking::with(['customer', 'service', 'location'])
            ->whereIn('status', ['awaiting_assignment', 'pending'])
            ->where('assignment_type', 'browsable')
            ->where(function ($query) {
                // Show immediate bookings OR scheduled bookings within 10 minutes window
                $query->where('schedule_type', 'now')
                    ->orWhere(function ($q) {
                    $q->where('schedule_type', 'scheduled')
                        ->where('scheduled_at', '<=', now()->addMinutes(10));
                });
            })
            ->when($request->booking_type, function ($q) use ($request) {
                return $q->where('booking_type', $request->booking_type);
            })
            ->whereHas('location', function ($q) use ($lat, $lng, $radius) {
                // Haversine formula
                $q->whereRaw("(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?", [$lat, $lng, $lat, $radius]);
            })
            ->get();

        return response()->json([
            'bookings' => $bookings->map->toThinArray()->values()
        ]);
    }

    /**
     * Get active direct requests for the authenticated therapist.
     */
    public function activeRequests(Request $request): JsonResponse
    {
        $user = $request->user();

        // --- NEW: Professional Wallet Balance Check ---
        if ($user->wallet_balance < 1000) {
            return response()->json([
                'message' => 'Insufficient Wallet Balance. A minimum maintaining balance of ₱1,000.00 is required to receive and accept booking requests. Please top up your wallet to proceed.',
                'wallet_balance' => (float) $user->wallet_balance,
                'is_online' => false
            ], 422);
        }
        $provider = $user->providers()->whereIn('type', ['therapist', 'store'])->first()
            ?? $user->providers()->first();

        if (!$provider && $user->role === 'therapist') {
            $provider = Provider::create([
                'user_id' => $user->id,
                'type' => 'therapist',
                'verification_status' => 'pending',
                'is_active' => true,
                'is_available' => false,
                'is_accepting_bookings' => false,
            ]);

            \App\Models\TherapistProfile::firstOrCreate(
                ['provider_id' => $provider->id],
                [
                    'bio' => 'Awaiting profile completion.',
                    'years_of_experience' => 0,
                    'specializations' => [],
                ]
            );
        }

        if (!$provider) {
            return response()->json([
                'message' => 'Therapist profile not found',
                'role' => $user->role
            ], 404);
        }

        $now = now();
        $threshold = $now->copy()->addMinutes(10);

        $bookings = Booking::with(['customer', 'service', 'location'])
            ->where('provider_id', $provider->id)
            ->where('status', 'pending')
            ->where('assignment_type', 'direct_request')
            ->where(function ($q) use ($threshold) {
                $q->where('schedule_type', 'now')
                    ->orWhere(function ($sq) use ($threshold) {
                        $sq->where('schedule_type', 'scheduled')
                            ->whereNotNull('scheduled_at')
                            ->where('scheduled_at', '<=', $threshold);
                    });
            })
            ->when($request->booking_type, function ($q) use ($request) {
                return $q->where('booking_type', $request->booking_type);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'bookings' => $bookings->map->toThinArray()->values()
        ]);
    }

    /**
     * Get dashboard statistics for the therapist.
     */
    public function dashboardStats(Request $request): JsonResponse
    {
        $user = $request->user();

        // Enforce Maintenance Balance Check
        if ($user->wallet_balance < 1000) {
            $p = $user->providers()->where('is_available', true)->first();
            if ($p) {
                $p->update(['is_available' => false]);
                ProviderLocation::where('provider_id', $p->id)
                    ->where('is_online', true)
                    ->update(['is_online' => false]);
            }
        }

        // STORE DASHBOARD STATS
        if ($user->customer_tier === 'store') {
            $provider = $user->providers()->where('type', 'store')->first();

            if (!$provider) {
                return response()->json(['message' => 'Store profile not found'], 404);
            }

            // Store Sessions: Total completed bookings
            $sessionsCount = Booking::where('provider_id', $provider->id)
                ->where('status', 'completed')
                ->count();

            // Active Therapists: (Mock logic for now - count ALL online therapists)
            // Ideally, this would be "Therapists currently checked in at this store"
            $activeTherapists = Provider::where('type', 'therapist')
                ->where('is_online', true)
                ->count();

            // Store Earnings Today
            $earningsToday = Booking::where('provider_id', $provider->id)
                ->where('status', 'completed')
                ->whereDate('created_at', today())
                ->sum('total_amount');

            return response()->json([
                'sessions' => $sessionsCount,
                'active_therapists' => $activeTherapists,
                'earnings_today' => $earningsToday,
                'rating' => number_format((float) ($provider->average_rating ?? 5.0), 1, '.', ''),
                'is_store' => true
            ]);
        }

        // THERAPIST DASHBOARD STATS
        $provider = $user->providers()->with('therapistProfile')->where('type', 'therapist')->first();

        if (!$provider) {
            return response()->json(['message' => 'Therapist profile not found'], 404);
        }

        // Sessions: Count of completed bookings (total)
        $sessionsCount = Booking::where('provider_id', $provider->id)
            ->where('status', 'completed')
            ->count();

        // Rating: From provider model, which is updated by ReviewObserver
        $rating = $provider->average_rating ?? 5.0;

        // Earnings Today: Sum of completed bookings created today
        $earningsToday = Booking::where('provider_id', $provider->id)
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->sum('total_amount');

        return response()->json([
            'sessions' => $sessionsCount,
            'rating' => number_format((float) $rating, 1, '.', ''),
            'earnings_today' => $earningsToday,
            'is_store' => false
        ]);
    }

    /**
     * Submit VIP Upgrade Application.
     */
    public function applyVip(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nickname' => 'nullable|string|max:100',
            'age' => 'nullable|integer|min:18|max:100',
            'address' => 'nullable|string|max:255',
            'experience' => 'nullable|integer|min:0',
            'skills' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'id_front' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'id_back' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'selfie_with_id' => 'nullable|file|mimes:jpeg,png,jpg|max:5120',
            'professional_license' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'gallery_images.*' => 'nullable|file|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        if ($user->role !== 'therapist') {
            return response()->json(['message' => 'Only therapists can apply for VIP upgrade.'], 403);
        }

        // Update User
        $userData = [];
        if ($request->has('nickname')) {
            $userData['nickname'] = $request->nickname;
        }
        if ($request->has('age')) {
            $userData['date_of_birth'] = now()->subYears($request->age)->startOfYear();
        }
        if (!empty($userData)) {
            $user->update($userData);
        }

        // Update Therapist Profile
        $provider = $user->providers()->where('type', 'therapist')->first();

        \Illuminate\Support\Facades\Log::info('applyVip called', ['user_id' => $user->id, 'provider_found' => (bool) $provider]);

        if ($provider) {
            $profile = $provider->therapistProfile()->firstOrCreate([], [
                'bio' => 'Awaiting profile completion.',
                'years_of_experience' => 0,
                'specializations' => [],
            ]);

            $profileData = [];
            if ($request->has('address')) {
                $profileData['base_address'] = $request->address;
            }
            if ($request->has('experience')) {
                $profileData['years_of_experience'] = $request->experience;
            }
            if ($request->has('skills')) {
                $profileData['specializations'] = array_map('trim', explode(',', $request->skills));
            }
            if ($request->has('bio')) {
                $profileData['bio'] = $request->bio;
            }

            // Handle Gallery Images
            if ($request->hasFile('gallery_images')) {
                $galleryPaths = $profile->gallery_images ?? [];
                foreach ($request->file('gallery_images') as $image) {
                    $path = $image->store('therapist-gallery', 'public');
                    $galleryPaths[] = env('APP_URL') . '/storage/' . $path;
                }
                $profileData['gallery_images'] = $galleryPaths;
            }

            $profileData['vip_status'] = 'pending';
            $profileData['vip_applied_at'] = now();
            $profile->update($profileData);

            // Handle Documents
            $documentTypes = ['id_front', 'id_back', 'selfie_with_id', 'professional_license'];
            foreach ($documentTypes as $type) {
                if ($request->hasFile($type)) {
                    $file = $request->file($type);
                    $path = $file->store('provider-documents', 'public');

                    // Create or update document record
                    // Note: We might want to replace existing docs of same type or just add new ones. 
                    // For simplicity, we add new ones. cleanup logic can be added later.
                    \App\Models\ProviderDocument::create([
                        'provider_id' => $provider->id,
                        'type' => $type,
                        'file_path' => $path, // Or full URL if preferred, consistency is key.
                        'file_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'uploaded_at' => now(),
                    ]);
                }
            }
        }

        // We don't change customer_tier to VIP yet.
        // In a real app, we'd set an application_status = 'pending'.
        // For now, we just return success.

        return response()->json([
            'message' => 'VIP application submitted successfully.',
            'user' => $user->load('provider.therapistProfile')
        ]);
    }
}
