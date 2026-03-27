<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminBookingController extends Controller
{
    /**
     * Display a listing of bookings (admin view).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Booking::with(['customer', 'provider.user', 'service', 'location']);

        // Status Filtering
        if ($request->has('status') && $request->status !== 'all') {
            $status = $request->status;
            if ($status === 'active') {
                $query->whereIn('status', ['en_route', 'arrived', 'in_progress', 'ongoing']);
            } elseif ($status === 'completed') {
                $query->where('status', 'completed');
            } elseif ($status === 'cancelled') {
                $query->where('status', 'cancelled');
            } elseif ($status === 'requested') {
                $query->whereIn('status', ['pending', 'awaiting_assignment', 'requested']);
            } else {
                 // specific status
                $query->where('status', $status);
            }
        }

        // Service Type Filtering
        if ($request->has('service_type') && $request->service_type !== 'all') {
            $serviceType = $request->service_type;
            $query->whereHas('service', function($q) use ($serviceType) {
                $q->where('name', 'like', "%{$serviceType}%");
            });
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('booking_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($subQ) use ($search) {
                      $subQ->where('first_name', 'like', "%{$search}%")
                           ->orWhere('last_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('provider.user', function($subQ) use ($search) {
                      $subQ->where('first_name', 'like', "%{$search}%")
                           ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        $bookings = $query->orderBy('created_at', 'desc')->paginate(15);
        
        // Transform using existing method if available, or manual map
        $collection = $bookings->getCollection()->map(function($booking) {
             return $booking->toThinArray();
        });

        // Get Counts (optimized)
        // Note: This adds overhead. Frontend can might request this separately or only on load.
        // For now, let's keep it simple and just do it.
        $stats = [
            'total' => Booking::count(),
            'active' => Booking::whereIn('status', ['en_route', 'arrived', 'in_progress', 'ongoing'])->count(),
            'completed' => Booking::where('status', 'completed')->count(),
            'cancelled' => Booking::where('status', 'cancelled')->count(),
            'requested' => Booking::whereIn('status', ['pending', 'awaiting_assignment', 'requested'])->count()
        ];

        return response()->json([
            'bookings' => $collection,
             'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'total' => $bookings->total(),
            ],
            'stats' => $stats
        ]);
    }
}
