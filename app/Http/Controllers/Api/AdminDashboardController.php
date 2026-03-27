<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        // 1. Active Bookings
        $activeBookingsCount = Booking::whereIn('status', [
            'en_route', 'arrived', 'in_progress', 'ongoing'
        ])->count();

        $bookingsToday = Booking::whereDate('scheduled_at', Carbon::today())->count();

        // 2. Online Therapists (Heartbeat within 5 minutes)
    $onlineTherapistsCount = Provider::where('type', 'therapist')
        ->where('is_available', true)
        ->whereHas('locations', function($q) {
            $q->where('recorded_at', '>=', Carbon::now()->subMinutes(30)); // Using 30 mins as a safer buffer for now
        })
        ->count();

        // 3. Total Revenue
        $totalRevenue = Booking::where('status', 'completed')->sum('total_amount');

        // 4. Pending Payouts (Mocked for now as we haven't implemented Payouts fully)
        // Adjust this query once Payout model is ready
        $pendingPayoutsAmount = 0; 
        $pendingPayoutsCount = 0;

        // 5. Recent Bookings (Limit 5)
        $recentBookings = Booking::with(['customer', 'provider.user', 'service'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($booking) {
                return $booking->toThinArray();
            });

        // 6. Revenue Chart Data (Last 6 months)
        $revenueData = Booking::select(
            DB::raw('sum(total_amount) as revenue'), 
            DB::raw("TO_CHAR(created_at, 'Mon') as month"),
            DB::raw("EXTRACT(MONTH FROM created_at) as month_num")
        )
        ->where('status', 'completed')
        ->where('created_at', '>=', Carbon::now()->subMonths(6))
        ->groupBy('month', 'month_num')
        ->orderBy('month_num')
        ->get();
        
        // Fill in missing months if needed, or send as is
        // For simplicity, sending as is. Frontend can handle mapping.

        // 7. Recent Alerts (Unified Feed)
        $alerts = collect();

        // New Messages (Last 5)
        $recentMessages = \App\Models\Message::with('sender')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        foreach ($recentMessages as $msg) {
            $isTherapist = ($msg->sender && $msg->sender->role === 'therapist');
            $roleLabel = $isTherapist ? 'Therapist' : 'Client';
            $type = $isTherapist ? 'therapist_message' : 'client_message';
            
            $alerts->push([
                'id' => 'msg-' . $msg->id,
                'type' => $type,
                'title' => $roleLabel . ' Message',
                'message' => $msg->sender ? ($msg->sender->first_name . ': ' . Str::limit($msg->content, 40)) : 'New message received',
                'time' => $msg->created_at->diffForHumans(),
                'original_time' => $msg->created_at
            ]);
        }

        // Low Ratings (<= 3 stars, Last 5)
        $lowReviews = \App\Models\Review::with(['user', 'provider.user'])
            ->where('rating', '<=', 3)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        foreach ($lowReviews as $review) {
            $alerts->push([
                'id' => 'rev-' . $review->id,
                'type' => 'low_rating',
                'title' => 'Low Rating',
                'message' => 'Therapist ' . ($review->provider->user->first_name ?? 'N/A') . ' received ' . $review->rating . ' stars',
                'time' => $review->created_at->diffForHumans(),
                'original_time' => $review->created_at
            ]);
        }

        // Cancelled Bookings (Last 5)
        $cancelledBookings = Booking::where('status', 'cancelled')
            ->orderBy('cancelled_at', 'desc')
            ->take(5)
            ->get();

        foreach ($cancelledBookings as $booking) {
            $alerts->push([
                'id' => 'can-' . $booking->id,
                'type' => 'cancellation',
                'title' => 'Booking Cancelled',
                'message' => 'Booking ' . $booking->booking_number . ' was cancelled',
                'time' => $booking->cancelled_at ? $booking->cancelled_at->diffForHumans() : $booking->updated_at->diffForHumans(),
                'original_time' => $booking->cancelled_at ?? $booking->updated_at
            ]);
        }

        // Sort by time and limit to 10
        $sortedAlerts = $alerts->sortByDesc('original_time')->take(10)->values();

        return response()->json([
            'stats' => [
                'active_bookings' => $activeBookingsCount,
                'bookings_today' => $bookingsToday,
                'online_therapists' => $onlineTherapistsCount,
                'total_revenue' => $totalRevenue,
                'pending_payouts_amount' => $pendingPayoutsAmount,
                'pending_payouts_count' => $pendingPayoutsCount,
            ],
            'recent_bookings' => $recentBookings,
            'revenue_chart' => $revenueData,
            'recent_alerts' => $sortedAlerts
        ]);
    }
}
