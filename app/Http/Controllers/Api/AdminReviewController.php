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
            'flagged_reviews' => 0, // Placeholder
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
                'status' => 'Published' // Default
            ];
        });

        return response()->json([
            'reviews' => $collection,
            'stats' => $stats,
             'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ]
        ]);
    }
}
