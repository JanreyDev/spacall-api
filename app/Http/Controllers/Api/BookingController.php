<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingLocation;
use App\Models\Provider;
use App\Models\Service;
use App\Events\BookingRequested;
use App\Events\BookingStatusUpdated;
use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use MatanYadaev\EloquentSpatial\Objects\Point;
use App\Services\VipService;

class BookingController extends Controller
{
    protected $vipService;

    public function __construct(VipService $vipService)
    {
        $this->vipService = $vipService;
    }
    /**
     * List user's bookings (Client or Therapist).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $query = Booking::with(['customer', 'provider.user', 'service', 'location']);

            if ($user->role === 'therapist') {
                $provider = $user->providers()->first();
                $query->where('provider_id', $provider->id);
            } else {
                $query->where('customer_id', $user->id);
            }

            if ($request->has('booking_type')) {
                $query->where('booking_type', $request->booking_type);
            }

            $bookings = $query->orderBy('created_at', 'desc')->paginate(15);
            $historyStatuses = ['completed', 'cancelled', 'no_show', 'expired', 'completed_pending_review'];

            // Transform the actual data to be as thin as possible to avoid transport corruption
            $collection = $bookings->getCollection()->map->toThinArray();

            $partitioned = $collection->partition(function ($booking) use ($historyStatuses) {
                return in_array($booking['status'], $historyStatuses);
            });

            return response()->json([
                'bookings' => $collection->values(),
                'current' => $partitioned[1]->values(),
                'history' => $partitioned[0]->values(),
                'meta' => [
                    'total' => $bookings->total(),
                    'per_page' => $bookings->perPage(),
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Bookings Index Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error fetching bookings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List therapists available for immediate booking.
     */
    public function availableTherapists(Request $request): JsonResponse
    {
        try {
            $lat = $request->query('latitude');
            $lng = $request->query('longitude');
            $radius = $request->query('radius', 30); // Default to 30km

            $includeOffline = $request->query('include_offline', false);

            $query = Provider::with([
                'user',
                'therapistProfile',
                'services',
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

            // Distance calculation and sorting if coords provided
            if ($lat && $lng) {
                // Ensure floating point values
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
            }

            $therapists = $query->get();

            return response()->json([
                'therapists' => $therapists
            ]);
        } catch (\Exception $e) {
            \Log::error('Therapist Fetch Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error fetching therapists',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new "Book Now" booking.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'provider_id' => 'nullable|exists:providers,id',
            'address' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'city' => 'required|string',
            'province' => 'required|string',
            'customer_notes' => 'nullable|string',
            'gender_preference' => 'nullable|string',
            'intensity_preference' => 'nullable|string',
            'booking_type' => 'nullable|in:home_service,in_store',
            'scheduled_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $service = Service::find($request->service_id);
        $provider = $request->provider_id ? Provider::find($request->provider_id) : null;

        if ($provider) {
            if (!$provider->is_active) {
                return response()->json(['message' => 'Therapist is no longer active.'], 422);
            }
            if (!$provider->is_available && !$request->has('scheduled_at') && $request->schedule_type !== 'scheduled') {
                return response()->json(['message' => 'Therapist is currently offline and cannot accept immediate bookings.'], 422);
            }
        }

        $isVipCustomer = in_array($request->user()->customer_tier, ['vip', 'platinum'], true);
        $defaultServicePrice = $isVipCustomer && $service->vip_price !== null
            ? $service->vip_price
            : $service->base_price;

        // Get therapist-specific price and calculate distance surcharge
        $actualPrice = $defaultServicePrice;
        $distanceKm = 0;
        $distanceSurcharge = 0;

        if ($provider) {
            $serviceDetails = $provider->services()->where('service_id', $service->id)->first();
            $actualPrice = $serviceDetails ? $serviceDetails->pivot->price : $defaultServicePrice;

            // Calculate distance if therapist has a base location
            $profile = $provider->therapistProfile;
            if ($profile && $profile->base_location_latitude && $profile->base_location_longitude) {
                $lat1 = (double) $request->latitude;
                $lon1 = (double) $request->longitude;
                $lat2 = (double) $profile->base_location_latitude;
                $lon2 = (double) $profile->base_location_longitude;

                $theta = $lon1 - $lon2;
                $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
                $dist = acos(min(max($dist, -1.0), 1.0));
                $distanceKm = rad2deg($dist) * 60 * 1.1515 * 1.609344;

                // Calculate surcharge from pivot settings
                if ($serviceDetails) {
                    $baseDist = $serviceDetails->pivot->base_distance_km ?? 5;
                    if ($distanceKm > $baseDist) {
                        $extraKm = $distanceKm - $baseDist;
                        $perKmSurcharge = $serviceDetails->pivot->per_km_surcharge ?? 0;
                        $distanceSurcharge = $extraKm * $perKmSurcharge;
                    }
                }
            }
        }

        $totalWithSurcharge = $actualPrice + $distanceSurcharge;

        $customer = $request->user();
        if ($customer->wallet_balance < $totalWithSurcharge) {
            return response()->json([
                'message' => 'Insufficient wallet balance.',
                'required' => $totalWithSurcharge,
                'current' => $customer->wallet_balance
            ], 422);
        }

        DB::beginTransaction();
        try {
            Log::info('Creating booking record...', [
                'data' => [
                    'customer_id' => $customer->id,
                    'provider_id' => $provider?->id,
                    'service_id' => $service->id,
                ]
            ]);

            // Map customer tier to database enum values
            $customerTier = $customer->customer_tier;
            if ($customerTier === 'classic') {
                $customerTier = 'normal';
            } elseif (!in_array($customerTier, ['normal', 'vip', 'platinum'])) {
                $customerTier = 'normal';
            }

            $status = ($request->scheduled_at || $provider) ? 'pending' : 'awaiting_assignment';

            $bookingData = [
                'customer_id' => $customer->id,
                'provider_id' => $provider?->id,
                'service_id' => $service->id,
                'booking_type' => $request->booking_type ?? 'home_service',
                'status' => $status,
                'customer_tier' => $customerTier,
                'assignment_type' => $provider ? 'direct_request' : 'browsable',
                'service_price' => $actualPrice,
                'total_amount' => $totalWithSurcharge,
                'customer_notes' => $request->customer_notes,
                'gender_preference' => $request->gender_preference,
                'intensity_preference' => $request->intensity_preference,
                'scheduled_at' => $request->scheduled_at,
                'schedule_type' => $request->scheduled_at ? 'scheduled' : 'now',
                'payment_method' => 'wallet',
                'payment_status' => 'pending', // Postponed until therapist accepts
                'duration_minutes' => $request->duration_minutes ?? $service->duration_minutes ?? 60,
            ];

            // REMOVED: Immediate Deduction for Wallet Payment
            // Deduction will now happen in updateStatus when therapist accepting.

            // Only add distance fields if they exist in the table schema
            try {
                if ($distanceKm > 0) {
                    $bookingData['distance_km'] = $distanceKm;
                }
                if ($distanceSurcharge > 0) {
                    $bookingData['distance_surcharge'] = $distanceSurcharge;
                    $bookingData['subtotal'] = $actualPrice;
                }
            } catch (\Exception $e) {
                Log::warning('Distance columns not available in bookings table', ['error' => $e->getMessage()]);
            }

            $booking = Booking::create($bookingData);

            Log::info('Booking created, creating location...', ['booking_id' => $booking->id]);

            // Create booking location with geography support
            BookingLocation::create([
                'booking_id' => $booking->id,
                'address' => $request->address,
                'city' => $request->city,
                'province' => $request->province,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'location' => new Point($request->latitude, $request->longitude),
                'distance_km' => $distanceKm,
            ]);

            // Set therapist to unavailable once booked if pre-selected
            if ($provider) {
                $provider->update(['is_available' => false]);
            }

            DB::commit();

            if ($provider && $booking->schedule_type === 'now') {
                // Direct booking — notify the specific therapist
                try {
                    event(new BookingRequested($booking));
                } catch (\Exception $e) {
                    \Log::error("Broadcasting BookingRequested (direct) failed: " . $e->getMessage());
                }
                $booking->update(['is_dispatched' => true]);

            } elseif (!$provider && $booking->schedule_type === 'now') {
                // Browsable booking — broadcast to ALL online+available therapists so they can claim it
                try {
                    $onlineProviders = \App\Models\Provider::where('is_available', true)
                        ->where('is_active', true)
                        ->get();

                    \Log::info('Browsable booking broadcast', [
                        'booking_id' => $booking->id,
                        'online_providers' => $onlineProviders->count(),
                        'provider_ids' => $onlineProviders->pluck('id')->toArray(),
                    ]);

                    foreach ($onlineProviders as $nearbyProvider) {
                        event(new BookingRequested($booking, $nearbyProvider->id));
                    }

                    $booking->update(['is_dispatched' => true]);
                } catch (\Exception $e) {
                    \Log::error("Broadcasting BookingRequested (browsable) failed: " . $e->getMessage());
                }
            }

            Log::info('Booking process complete, loading relations...');

            // Avoid loading provider.user if provider is null
            $booking->load('location');
            if ($booking->provider_id) {
                $booking->load('provider.user');
            }

            return response()->json([
                'message' => 'Booking created successfully',
                'booking' => $booking->toThinArray()
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Booking creation failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'user_id' => $request->user()->id
            ]);
            return response()->json(['message' => 'Failed to create booking', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update booking status (Therapist only).
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);
        $user = $request->user();
        $isTherapist = ($user->role === 'therapist');
        $isCustomer = ($user->id === $booking->customer_id);

        Log::info('--- BOOKING STATUS UPDATE START ---', [
            'booking_id' => $id,
            'new_status' => $request->status,
            'current_status' => $booking->status,
            'assignment_type' => $booking->assignment_type,
            'provider_id' => $booking->provider_id,
            'is_customer' => $isCustomer,
            'is_therapist' => $isTherapist
        ]);

        if (!$isTherapist && !$isCustomer) {
            Log::warning('Unauthorized booking status update attempt', ['user_id' => $user->id, 'booking_id' => $id]);
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:accepted,en_route,arrived,in_progress,completed,cancelled',
            'travel_mode' => 'nullable|in:driving,motorcycle,walking',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $newStatus = $request->status;

        // Role-based Restrictions
        if ($isCustomer && !in_array($newStatus, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'Customers can only mark as completed or cancelled.'], 403);
        }

        DB::beginTransaction();
        try {
            if ($newStatus === 'accepted' && $isTherapist) {
                $therapistProvider = $user->providers()->where('type', 'therapist')->first();
                $storeProvider = $user->providers()->where('type', 'store')->first();
                $provider = $therapistProvider ?? $storeProvider;
                if (!$provider) {
                    return response()->json(['message' => 'Therapist profile not found'], 404);
                }

                // If booking is assigned, validate assignment ownership.
                if ($booking->provider_id) {
                    $assignedToCurrentProvider = ((int) $booking->provider_id === (int) $provider->id);

                    // For in-store bookings, allow therapists who belong to the same store profile.
                    if (!$assignedToCurrentProvider && $booking->booking_type === 'in_store' && $therapistProvider) {
                        $assignedStoreProvider = Provider::with('storeProfile')->find($booking->provider_id);
                        $assignedStoreProfileId = optional($assignedStoreProvider?->storeProfile)->id;
                        $therapistStoreProfileId = optional($therapistProvider->therapistProfile)->store_profile_id;

                        if (
                            $assignedStoreProfileId &&
                            $therapistStoreProfileId &&
                            ((int) $assignedStoreProfileId === (int) $therapistStoreProfileId)
                        ) {
                            $assignedToCurrentProvider = true;
                            // Re-assign booking to the accepting therapist provider.
                            $provider = $therapistProvider;
                        }
                    }

                    if (!$assignedToCurrentProvider) {
                        return response()->json(['message' => 'This booking was not assigned to you.'], 403);
                    }
                }

                if ($booking->status !== 'awaiting_assignment' && $booking->status !== 'pending') {
                    return response()->json(['message' => 'This booking is no longer available.'], 422);
                }

                $isInStore = $booking->booking_type === 'in_store';

                // --- NEW: Deduct Wallet Balance on Acceptance ---
                $customer = $booking->customer;
                $amount = $booking->total_amount;

                if ($customer->wallet_balance < $amount) {
                    return response()->json([
                        'message' => 'Insufficient wallet balance to proceed with this therapist.',
                        'required' => $amount,
                        'current' => $customer->wallet_balance
                    ], 422);
                }

                $customer->decrement('wallet_balance', $amount);

                // Create Transaction record for Client
                $transaction = new Transaction();
                $transaction->transactable_type = get_class($customer);
                $transaction->transactable_id = $customer->id;
                $transaction->type = 'booking';
                $transaction->amount = $amount;
                $transaction->currency = 'PHP';
                $transaction->status = 'completed';
                $transaction->meta = [
                    'booking_id' => $booking->id,
                    'description' => 'Session Payment: ' . ($booking->service?->name ?? 'Service'),
                ];
                $transaction->completed_at = now();
                $transaction->save();

                Log::info('Wallet deduction on therapist acceptance', [
                    'booking_id' => $booking->id,
                    'customer_id' => $customer->id,
                    'amount' => $amount,
                    'new_balance' => $customer->wallet_balance,
                    'transaction_id' => $transaction->transaction_id ?? 'N/A'
                ]);
                // ------------------------------------------------

                $booking->update([
                    'status' => 'accepted',
                    'payment_status' => 'held', // Funds are now held
                    'accepted_at' => now(),
                    'provider_id' => $provider->id
                ]);

                // Mark provider as busy and offline
                $provider->update(['is_available' => false]);
                \App\Models\ProviderLocation::where('provider_id', $provider->id)->update(['is_online' => false, 'recorded_at' => now()]);
                try {
                    broadcast(new \App\Events\TherapistAvailabilityChanged($provider->id, false))->toOthers();
                } catch (\Exception $e) {
                }
            } elseif ($newStatus === 'en_route' && $isTherapist) {
                $booking->update([
                    'status' => 'en_route',
                    'travel_mode' => $request->input('travel_mode', $booking->travel_mode ?? 'driving'),
                ]);
            } elseif ($newStatus === 'arrived' && $isTherapist) {
                $booking->update(['status' => 'arrived']);
            } elseif ($newStatus === 'in_progress' && $isTherapist) {
                $booking->update(['status' => 'in_progress', 'started_at' => now()]);
            } elseif ($newStatus === 'cancelled' && $isTherapist) {
                $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                if ($booking->provider) {
                    $booking->provider->update(['is_available' => true]);
                    \App\Models\ProviderLocation::where('provider_id', $booking->provider->id)->update(['is_online' => true, 'recorded_at' => now()]);
                    try {
                        broadcast(new \App\Events\TherapistAvailabilityChanged($booking->provider->id, true))->toOthers();
                    } catch (\Exception $e) {
                    }
                }
            } elseif ($newStatus === 'completed') {
                if ($isTherapist) {
                    // Therapist says they are done
                    $booking->update(['status' => 'completed', 'completed_at' => now()]);
                    if ($booking->provider) {
                        $booking->provider->update(['is_available' => true]);
                        \App\Models\ProviderLocation::where('provider_id', $booking->provider->id)->update(['is_online' => true, 'recorded_at' => now()]);
                        try {
                            broadcast(new \App\Events\TherapistAvailabilityChanged($booking->provider->id, true))->toOthers();
                        } catch (\Exception $e) {
                        }
                    }

                    // Note: Money doesn't move yet. Waiting for Customer Review.

                    // VIP SYSTEM: Track completed booking
                    $this->vipService->updateStats($booking->provider, 'bookings', 1);
                }

                if ($isCustomer) {
                    // Customer can acknowledge completion, but payment is now triggered by Review
                    // We just ensure status is completed.
                    if ($booking->status !== 'completed') {
                        return response()->json(['message' => 'Waiting for therapist to mark the service as completed first.'], 422);
                    }
                }
            } elseif ($newStatus === 'cancelled') {
                $booking->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by' => $isTherapist ? 'provider' : 'customer'
                ]);
                if ($booking->provider) {
                    $booking->provider->update(['is_available' => true]);
                    \App\Models\ProviderLocation::where('provider_id', $booking->provider->id)->update(['is_online' => true, 'recorded_at' => now()]);
                    try {
                        broadcast(new \App\Events\TherapistAvailabilityChanged($booking->provider->id, true))->toOthers();
                    } catch (\Exception $e) {
                    }
                }
            }


            Log::info("DEBUG: updateStatus reached end", [
                'newStatus' => $newStatus,
                'assignment_type' => $booking->assignment_type,
                'booking_id' => $booking->id
            ]);

            DB::commit();

            // Broadcast that the booking has been claimed or cancelled to remove it from others
            if ($newStatus === 'accepted' || ($newStatus === 'cancelled' && $booking->assignment_type === 'browsable')) {
                try {
                    Log::info("Broadcasting BookingClaimed/Cancelled", ['booking_id' => $booking->id, 'status' => $newStatus, 'assignment_type' => $booking->assignment_type]);
                    broadcast(new \App\Events\BookingClaimed($booking->id, $newStatus === 'cancelled' ? 'cancelled' : 'claimed'))->toOthers();
                } catch (\Exception $e) {
                    \Log::error("Broadcasting BookingClaimed/Cancelled failed: " . $e->getMessage());
                }
            }

            // Reload booking with relations and broadcast the update
            $booking->refresh()->load(['customer', 'location', 'service', 'provider.user']);
            try {
                // Regular status update on private booking channel
                broadcast(new BookingStatusUpdated($booking))->toOthers();

                // If it's a direct request being cancelled, also notify the specific therapist on their private channel
                if ($newStatus === 'cancelled' && $booking->assignment_type === 'direct_request' && $booking->provider_id) {
                    Log::info("Broadcasting BookingRequested (Cancelled) to therapist", ['booking_id' => $booking->id, 'provider_id' => $booking->provider_id]);
                    broadcast(new \App\Events\BookingRequested($booking, $booking->provider_id))->toOthers();
                }
            } catch (\Exception $e) {
                \Log::error("Broadcasting Status Update failed: " . $e->getMessage());
            }

            return response()->json([
                'message' => 'Status updated successfully',
                'booking' => $booking->toThinArray()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update status', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Track booking status and therapist location.
     */
    public function track($id): JsonResponse
    {
        $booking = Booking::with([
            'provider.user',
            'provider.therapistProfile',
            'provider.locations' => function ($q) {
                $q->latest()->limit(1);
            }
        ])->findOrFail($id);

        $therapistLocation = null;
        $therapistName = null;

        if ($booking->provider) {
            $therapistLocation = $booking->provider->locations->first();
            if ($booking->provider->user && $booking->provider->user->nickname) {
                $therapistName = $booking->provider->user->nickname;
            } else {
                $therapistName = 'Therapist';
            }
        }

        $etaMinutes = null;
        if ($therapistLocation && $booking->location) {
            // Check if location is fresh (within last 2 minutes)
            $recordedAt = $therapistLocation->recorded_at ?? $therapistLocation->created_at;
            $lastUpdate = \Carbon\Carbon::parse($recordedAt);

            if ($lastUpdate->diffInMinutes(now()) <= 2) {
                $lat1 = $therapistLocation->latitude;
                $lon1 = $therapistLocation->longitude;
                $lat2 = $booking->location->latitude;
                $lon2 = $booking->location->longitude;

                // Haversine Formula for distance in km
                $earthRadius = 6371;
                $dLat = deg2rad($lat2 - $lat1);
                $dLon = deg2rad($lon2 - $lon1);
                $a = sin($dLat / 2) * sin($dLat / 2) +
                    cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
                    sin($dLon / 2) * sin($dLon / 2);
                $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                $distanceKm = $earthRadius * $c;

                $mode = $booking->travel_mode ?? 'driving';
                $speedKmh = match ($mode) {
                    'walking' => 5,
                    'motorcycle' => 30,
                    default => 20, // driving
                };

                $calcEta = ceil(($distanceKm / $speedKmh) * 60);

                if ($calcEta > 0) {
                    $etaMinutes = max(1, $calcEta);
                }
            }
        }

        return response()->json([
            'booking_status' => $booking->status,
            'travel_mode' => $booking->travel_mode,
            'payment_status' => $booking->payment_status,
            'therapist_location' => $therapistLocation,
            'therapist_name' => $therapistName,
            'therapist_photo_url' => $booking->provider && $booking->provider->user ? $booking->provider->user->id_selfie_photo_url : null,
            'eta_minutes' => $etaMinutes,
            'booking' => $booking->toThinArray(),
        ]);
    }

    /**
     * Extend an active session.
     */
    public function extend(Request $request, $id): JsonResponse
    {
        $booking = Booking::with(['service', 'provider.user'])->findOrFail($id);
        $customer = $request->user();

        if ($customer->id !== $booking->customer_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($booking->status !== 'in_progress') {
            return response()->json(['message' => 'Only active sessions can be extended.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'additional_minutes' => 'required|integer|in:30,60,120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $additionalMinutes = $request->additional_minutes;

        // Calculate price based on the booking's service rate
        $originalPrice = $booking->service_price;
        $originalServiceDuration = $booking->service?->duration_minutes ?? 60;

        $extensionPrice = ($originalPrice / $originalServiceDuration) * $additionalMinutes;
        $extensionPrice = round($extensionPrice, 2);

        if ($customer->wallet_balance < $extensionPrice) {
            return response()->json([
                'message' => 'Insufficient wallet balance.',
                'required' => $extensionPrice,
                'current' => $customer->wallet_balance
            ], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Deduct from Customer
            $customer->decrement('wallet_balance', $extensionPrice);

            // Create Transaction record for Client
            $transaction = new Transaction();
            $transaction->transactable_type = get_class($customer);
            $transaction->transactable_id = $customer->id;
            $transaction->type = 'booking'; // Use 'booking' so it shows correctly in client app
            $transaction->amount = $extensionPrice;
            $transaction->currency = 'PHP';
            $transaction->status = 'completed';
            $transaction->meta = [
                'booking_id' => $booking->id,
                'description' => 'Session Extension (' . $additionalMinutes . ' mins)',
            ];
            $transaction->completed_at = now();
            $transaction->save();

            // 2. Increment Therapist Wallet (Immediate payment for extension)
            if ($booking->provider && $booking->provider->user) {
                $booking->provider->user->increment('wallet_balance', $extensionPrice);
                // Also update provider earnings record
                $booking->provider->increment('total_earnings', $extensionPrice);
            }

            if (is_null($booking->duration_minutes)) {
                $booking->duration_minutes = $booking->service?->duration_minutes ?? 60;
                $booking->save();
            }

            Log::info('Session extension values before:', [
                'booking_id' => $booking->id,
                'current_duration' => $booking->duration_minutes,
                'adding' => $additionalMinutes
            ]);

            // 3. Update Booking
            $booking->increment('duration_minutes', $additionalMinutes);
            $booking->increment('total_amount', $extensionPrice);

            // VIP SYSTEM: Track session extension
            if ($booking->provider) {
                $this->vipService->updateStats($booking->provider, 'extensions', 1);
            }

            // Log the extension
            Log::info('Session extension values after:', [
                'booking_id' => $booking->id,
                'new_duration' => $booking->duration_minutes,
            ]);

            DB::commit();

            // Reload and return
            $booking->refresh()->load(['customer', 'location', 'service', 'provider.user']);

            Log::info('Session extension after refresh:', [
                'booking_id' => $booking->id,
                'refreshed_duration' => $booking->duration_minutes,
                'thin_array_check' => $booking->toThinArray()['duration_minutes']
            ]);

            // Broadcast the update
            try {
                event(new BookingStatusUpdated($booking));
            } catch (\Exception $e) {
                \Log::error("Broadcasting BookingStatusUpdated (extend) failed: " . $e->getMessage());
            }

            return response()->json([
                'message' => 'Session extended successfully',
                'extension_price' => $extensionPrice,
                'booking' => $booking->toThinArray()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Session extension failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to extend session', 'error' => $e->getMessage()], 500);
        }
    }

    public function getMessages(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $booking = Booking::findOrFail($id);

        // Check if user is participant
        if ($user->id !== $booking->customer_id) {
            $provider = $user->providers()->first();
            if (!$provider || $provider->id !== $booking->provider_id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $messages = Message::with('sender')
            ->where('booking_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'messages' => $messages->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'content' => $msg->content,
                    'sender_id' => $msg->sender_id,
                    'sender_name' => $msg->sender->first_name . ' ' . $msg->sender->last_name,
                    'created_at' => $msg->created_at->toIso8601String(),
                ];
            })
        ]);
    }

    public function sendMessage(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $booking = Booking::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if user is participant
        $recipientId = null;
        if ($user->id === $booking->customer_id) {
            $recipientId = $booking->provider?->user_id;
        } else {
            $provider = $user->providers()->first();
            if ($provider && $provider->id === $booking->provider_id) {
                $recipientId = $booking->customer_id;
            }
        }

        if (!$recipientId) {
            return response()->json(['message' => 'Unauthorized or recipient not found'], 403);
        }

        $message = Message::create([
            'booking_id' => $id,
            'sender_id' => $user->id,
            'recipient_id' => $recipientId,
            'content' => $request->content,
            'sent_at' => now(),
        ]);

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Exception $e) {
            \Log::error("Broadcasting MessageSent failed: " . $e->getMessage());
        }

        return response()->json([
            'message' => [
                'id' => $message->id,
                'content' => $message->content,
                'sender_id' => $message->sender_id,
                'sender_name' => $user->first_name . ' ' . $user->last_name,
                'created_at' => $message->created_at->toIso8601String(),
            ]
        ]);
    }

    public function verifyCompletion(Request $request, $id)
    {
        $request->validate([
            'verification_code' => 'required|string|size:6',
        ]);

        $booking = Booking::with(['customer', 'provider.user', 'service'])->findOrFail($id);
        $user = $request->user();

        // 1. Ensure the user is the provider of this booking
        if ($user->id !== $booking->provider->user_id) {
            return response()->json(['message' => 'Unauthorized. Only the assigned therapist can verify completion.'], 403);
        }

        // 2. Validate booking status
        if ($booking->status !== 'completed') {
            return response()->json(['message' => 'Booking must be completed before verification.'], 422);
        }

        // 3. Verify the code
        if ($request->verification_code !== $booking->verification_code) {
            return response()->json(['message' => 'Invalid verification code.'], 422);
        }

        // 4. Process Payment Release
        DB::beginTransaction();
        try {
            if ($booking->payment_status === 'held' || $booking->payment_status === 'pending') {
                if ($booking->payment_method === 'wallet') {
                    $totalAmount = $booking->total_amount ?? $booking->service_price;
                    $commissionRate = 0.10; // 10%
                    $commissionFee = $totalAmount * $commissionRate;
                    $netPayout = $totalAmount - $commissionFee;

                    // Increment Therapist's wallet
                    $provider = $booking->provider;
                    $provider->user->increment('wallet_balance', $netPayout);

                    // Create Transaction record
                    Transaction::create([
                        'transactable_type' => get_class($provider->user),
                        'transactable_id' => $provider->user->id,
                        'type' => 'booking',
                        'amount' => $netPayout,
                        'currency' => 'PHP',
                        'status' => 'completed',
                        'meta' => [
                            'booking_id' => $booking->id,
                            'description' => 'Payment for ' . ($booking->service?->name ?? 'Session'),
                            'net_payout' => $netPayout,
                            'total_amount' => $totalAmount,
                            'commission_fee' => $commissionFee
                        ],
                        'completed_at' => now(),
                    ]);

                    // Update Provider Stats
                    $provider->increment('total_earnings', $totalAmount);

                    Log::info('Payment released via verification code', [
                        'booking_id' => $booking->id,
                        'provider_id' => $provider->id,
                        'net_payout' => $netPayout
                    ]);
                }

                $booking->update([
                    'payment_status' => 'paid',
                    'verification_code' => null // Clear code after use
                ]);
            }

            DB::commit();

            // Broadcast the final status update
            try {
                broadcast(new \App\Events\BookingStatusUpdated($booking->fresh()))->toOthers();
            } catch (\Exception $e) {
                Log::error("Broadcasting BookingStatusUpdated failed in verifyCompletion: " . $e->getMessage());
            }

            return response()->json([
                'message' => 'Verification successful. Payment has been released to your wallet.',
                'payment_status' => 'paid'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Verification Error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to verify completion', 'error' => $e->getMessage()], 500);
        }
    }
}
