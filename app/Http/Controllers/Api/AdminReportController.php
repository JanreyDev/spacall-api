<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminReportController extends Controller
{
    public function index(): JsonResponse
    {
        // 1. Revenue Trends (Last 30 days)
        // Using created_at instead of scheduled_at for trends as it's more reliable for revenue reporting
        $revenueTrends = Booking::select(
            DB::raw("DATE(created_at) as date"),
            DB::raw("CAST(SUM(total_amount) as FLOAT) as revenue"),
            DB::raw("COUNT(*) as bookings")
        )
        ->where('status', 'completed')
        ->where('created_at', '>=', Carbon::now()->subDays(30))
        ->groupBy('date')
        ->orderBy('date')
        ->get();

        // 2. Service Popularity
        $servicePopularity = Booking::join('services', 'bookings.service_id', '=', 'services.id')
            ->select('services.name as service', DB::raw("COUNT(bookings.id) as bookings"), DB::raw("CAST(SUM(bookings.total_amount) as FLOAT) as revenue"))
            ->where('bookings.status', 'completed')
            ->groupBy('services.name')
            ->orderByDesc('bookings')
            ->take(8)
            ->get();

        // 3. Peak Booking Hours (Heatmap)
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $heatmapData = [];
        foreach ($days as $day) {
            $heatmapData[$day] = array_fill(0, 9, 0); 
        }

        // Using created_at for heatmap if scheduled_at is null
        $bookingTimes = Booking::select(
            DB::raw("TRIM(TO_CHAR(COALESCE(scheduled_at, created_at), 'Day')) as day_name"),
            DB::raw("EXTRACT(HOUR FROM COALESCE(scheduled_at, created_at)) as hour"),
            DB::raw("COUNT(*) as count")
        )
        ->where('status', '!=', 'cancelled')
        ->groupBy('day_name', 'hour')
        ->get();

        foreach ($bookingTimes as $bt) {
            $day = substr($bt->day_name, 0, 3);
            $hour = (int)$bt->hour;
            
            if ($hour >= 6 && $hour <= 22) {
                $slotIndex = floor(($hour - 6) / 2);
                 if (isset($heatmapData[$day]) && isset($heatmapData[$day][$slotIndex])) {
                    $heatmapData[$day][$slotIndex] += $bt->count;
                }
            }
        }

        $formattedHeatmap = [];
        foreach ($days as $day) {
            $formattedHeatmap[] = [
                'day' => $day,
                'hours' => $heatmapData[$day]
            ];
        }

        // 4. Geographic Distribution (Using booking_locations join)
        $geoData = Booking::join('booking_locations', 'bookings.id', '=', 'booking_locations.booking_id')
             ->select(
                'booking_locations.city as area',
                DB::raw("COUNT(bookings.id) as bookings"),
                DB::raw("CAST(SUM(bookings.total_amount) as FLOAT) as revenue")
             )
             ->where('bookings.status', 'completed')
             ->groupBy('booking_locations.city')
             ->orderByDesc('bookings')
             ->take(5)
             ->get();
             
        $totalGeoBookings = $geoData->sum('bookings');
        $formattedGeoData = $geoData->map(function($item) use ($totalGeoBookings) {
            $areaName = trim($item->area) ?: 'Metro Manila';

            return [
                'area' => $areaName,
                'bookings' => (int)$item->bookings,
                'revenue' => (float)$item->revenue,
                'percentage' => $totalGeoBookings > 0 ? round(($item->bookings / $totalGeoBookings) * 100) : 0
            ];
        });

        return response()->json([
            'revenue_trends' => $revenueTrends,
            'service_popularity' => $servicePopularity,
            'peak_hours' => $formattedHeatmap,
            'geographic_distribution' => $formattedGeoData
        ]);
    }
}

