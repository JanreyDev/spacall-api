<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminReviewController extends Controller
{
    /**
     * Display a listing of reviews (admin view).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Review::with(['user', 'provider.user', 'booking.service']);

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('body', 'like', "%{$search}%")
                  ->orWhereHas('user', function($subQ) use ($search) {
                      $subQ->where('first_name', 'like', "%{$search}%")
                           ->orWhere('last_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('provider.user', function($subQ) use ($search) {
                      $subQ->where('first_name', 'like', "%{$search}%")
                           ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        // Ratings Filter
        if ($request->has('rating') && $request->rating !== 'all') {
            $query->where('rating', $request->rating);
        }

        $reviews = $query->orderBy('created_at', 'desc')->paginate(15);
        
        // Stats
        $totalReviews = Review::count();
        $avgRating = Review::avg('rating') ?? 0;
        $flaggedReviews = Review::where('rating', '<=', 2)->count();
        
        // Find top rated therapist (simplified)
        // In a real app with millions of rows, this should be cached or pre-calculated
        $topTherapist = Provider::where('type', 'therapist')
            ->where('total_reviews', '>', 0)
            ->orderBy('average_rating', 'desc')
            ->with('user')
            ->first();

        $stats = [
            'total_reviews' => $totalReviews,
            'avg_rating' => round($avgRating, 1),
            'flagged_reviews' => $flaggedReviews,
            'top_rated_therapist' => $topTherapist ? [
                'name' => $topTherapist->user->first_name,
                'rating' => $topTherapist->average_rating
            ] : null
        ];

        // Transform collection
        $collection = $reviews->getCollection()->map(function($review) {
            return [
                'id' => $review->id,
                'booking_id' => $review->booking_id,
                'rating' => $review->rating,
                'comment' => $review->body,
                'created_at' => $review->created_at,
                'client' => $review->user ? [
                    'id' => $review->user->id,
                    'name' => $review->user->first_name . ' ' . $review->user->last_name,
                    'avatar' => $review->user->profile_photo_url
                ] : null,
                'therapist' => ($review->provider && $review->provider->user) ? [
                    'id' => $review->provider->id,
                    'name' => $review->provider->user->first_name . ' ' . $review->provider->user->last_name,
                    'avatar' => $review->provider->user->profile_photo_url
                ] : null,
                'service_name' => $review->booking && $review->booking->service ? $review->booking->service->name : 'Unknown Service',
                'status' => $review->rating <= 2 ? 'Flagged' : 'Published'
            ];
        });

        $ratingRows = Review::select(
            'provider_id',
            DB::raw('AVG(rating) as average_rating'),
            DB::raw('COUNT(*) as total_reviews'),
            DB::raw('SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_stars'),
            DB::raw('SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_stars'),
            DB::raw('SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_stars'),
            DB::raw('SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_stars'),
            DB::raw('SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_stars')
        )
            ->whereNotNull('provider_id')
            ->groupBy('provider_id')
            ->orderByDesc('average_rating')
            ->take(10)
            ->get();

        $providerIds = $ratingRows->pluck('provider_id')->unique()->values();
        $providers = Provider::with('user')
            ->whereIn('id', $providerIds)
            ->get()
            ->keyBy('id');

        $therapistRatings = $ratingRows->map(function ($row) use ($providers) {
            $provider = $providers->get($row->provider_id);
            $user = $provider ? $provider->user : null;

            return [
                'therapist_id' => (string) $row->provider_id,
                'therapist_name' => $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : 'Unknown Therapist',
                'therapist_avatar' => $user ? $user->profile_photo_url : null,
                'average_rating' => round((float) $row->average_rating, 2),
                'total_reviews' => (int) $row->total_reviews,
                'five_stars' => (int) $row->five_stars,
                'four_stars' => (int) $row->four_stars,
                'three_stars' => (int) $row->three_stars,
                'two_stars' => (int) $row->two_stars,
                'one_stars' => (int) $row->one_stars,
                'recent_trend' => 'stable',
            ];
        })->values();

        return response()->json([
            'reviews' => $collection,
            'therapist_ratings' => $therapistRatings,
            'stats' => $stats,
             'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ]
        ]);
    }
}
