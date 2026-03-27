<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    public function store(Request $request, $bookingId)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $booking = Booking::with(['customer', 'provider.user'])->findOrFail($bookingId);
        $user = $request->user();

        // Ensure the user is the customer of this booking
        if ($user->id !== $booking->customer_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Ensure booking is completed
        if ($booking->status !== 'completed') {
            return response()->json(['message' => 'Cannot review an incomplete service.'], 422);
        }

        // Ensure not already reviewed
        if (Review::where('booking_id', $bookingId)->exists()) {
            return response()->json(['message' => 'You have already reviewed this service.'], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Create Review
            $review = Review::create([
                'booking_id' => $booking->id,
                'provider_id' => $booking->provider_id,
                'user_id' => $user->id,
                'rating' => $request->rating,
                'body' => $request->comment,
            ]);

            // 2. Generate 6-digit verification code
            $verificationCode = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $booking->update([
                'verification_code' => $verificationCode
            ]);

            DB::commit();

            // Broadcast the update so the therapist app knows the code is ready
            try {
                broadcast(new \App\Events\BookingStatusUpdated($booking->fresh()))->toOthers();
            } catch (\Exception $e) {
                \Log::error("Broadcasting BookingStatusUpdated failed in ReviewController: " . $e->getMessage());
            }

            return response()->json([
                'message' => 'Review submitted successfully. Please provide the verification code to your therapist.',
                'review' => $review,
                'verification_code' => $verificationCode,
                'payment_status' => $booking->fresh()->payment_status
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Review Submission Error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to submit review', 'error' => $e->getMessage()], 500);
        }
    }
}
