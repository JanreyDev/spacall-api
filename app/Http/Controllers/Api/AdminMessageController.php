<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = strtolower((string) $request->query('status', 'all'));

        $query = Booking::with([
            'customer:id,first_name,last_name,profile_photo_url',
            'provider.user:id,first_name,last_name,profile_photo_url',
        ])
            ->withCount([
                'messages as unread_count' => function ($q) {
                    $q->where('is_read', false);
                },
            ])
            ->whereHas('messages');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('booking_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($subQ) use ($search) {
                        $subQ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('provider.user', function ($subQ) use ($search) {
                        $subQ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('messages', function ($subQ) use ($search) {
                        $subQ->where('content', 'like', "%{$search}%");
                    });
            });
        }

        if ($status === 'active') {
            $query->whereIn('status', ['pending', 'accepted', 'en_route', 'arrived', 'in_progress', 'ongoing']);
        } elseif ($status === 'completed') {
            $query->whereIn('status', ['completed']);
        }

        $bookings = $query->latest()->take(100)->get();
        $bookingIds = $bookings->pluck('id');

        $latestMessages = Message::with('sender')
            ->whereIn('booking_id', $bookingIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('booking_id')
            ->map(function ($group) {
                return $group->first();
            });

        $conversations = $bookings->map(function ($booking) use ($latestMessages) {
            $latest = $latestMessages->get($booking->id);
            $conversationStatus = $this->mapConversationStatus($booking->status, $latest?->content);

            return [
                'id' => (string) $booking->id,
                'booking_id' => (string) ($booking->booking_number ?? $booking->id),
                'booking_numeric_id' => $booking->id,
                'client_id' => (string) ($booking->customer?->id ?? ''),
                'client_name' => trim(($booking->customer?->first_name ?? '') . ' ' . ($booking->customer?->last_name ?? '')),
                'client_avatar' => $booking->customer?->profile_photo_url,
                'therapist_id' => (string) ($booking->provider?->id ?? ''),
                'therapist_name' => trim(($booking->provider?->user?->first_name ?? '') . ' ' . ($booking->provider?->user?->last_name ?? '')),
                'therapist_avatar' => $booking->provider?->user?->profile_photo_url,
                'last_message_at' => $latest?->created_at?->toIso8601String() ?? $booking->updated_at?->toIso8601String(),
                'last_message' => $latest ? [
                    'id' => (string) $latest->id,
                    'content' => $latest->content,
                    'sender_name' => trim(($latest->sender?->first_name ?? '') . ' ' . ($latest->sender?->last_name ?? '')),
                    'created_at' => $latest->created_at?->toIso8601String(),
                ] : null,
                'status' => $conversationStatus,
                'unread_count' => (int) ($booking->unread_count ?? 0),
            ];
        });

        if ($status === 'flagged') {
            $conversations = $conversations->filter(function ($conversation) {
                return $conversation['status'] === 'Flagged';
            })->values();
        }

        return response()->json([
            'conversations' => $conversations->values(),
            'stats' => [
                'total' => $conversations->count(),
                'active' => $conversations->where('status', 'Active')->count(),
                'flagged' => $conversations->where('status', 'Flagged')->count(),
            ],
        ]);
    }

    public function show(int $bookingId): JsonResponse
    {
        $booking = Booking::with([
            'customer:id,first_name,last_name,profile_photo_url',
            'provider.user:id,first_name,last_name,profile_photo_url',
            'messages.sender:id,first_name,last_name,profile_photo_url',
        ])->findOrFail($bookingId);

        $messages = $booking->messages
            ->sortBy('created_at')
            ->values()
            ->map(function ($message) use ($booking) {
                $isClient = (int) $message->sender_id === (int) $booking->customer_id;
                return [
                    'id' => (string) $message->id,
                    'sender' => $isClient ? 'Client' : 'Therapist',
                    'sender_id' => (string) $message->sender_id,
                    'sender_name' => trim(($message->sender?->first_name ?? '') . ' ' . ($message->sender?->last_name ?? '')),
                    'sender_avatar' => $message->sender?->profile_photo_url,
                    'content' => $message->content,
                    'timestamp' => $message->created_at?->toIso8601String(),
                    'read' => (bool) $message->is_read,
                    'flagged' => $this->isFlaggedContent($message->content),
                ];
            });

        $status = $this->mapConversationStatus($booking->status, $messages->last()['content'] ?? null);

        return response()->json([
            'conversation' => [
                'id' => (string) $booking->id,
                'booking_id' => (string) ($booking->booking_number ?? $booking->id),
                'booking_numeric_id' => $booking->id,
                'client_id' => (string) ($booking->customer?->id ?? ''),
                'client_name' => trim(($booking->customer?->first_name ?? '') . ' ' . ($booking->customer?->last_name ?? '')),
                'client_avatar' => $booking->customer?->profile_photo_url,
                'therapist_id' => (string) ($booking->provider?->id ?? ''),
                'therapist_name' => trim(($booking->provider?->user?->first_name ?? '') . ' ' . ($booking->provider?->user?->last_name ?? '')),
                'therapist_avatar' => $booking->provider?->user?->profile_photo_url,
                'last_message_at' => $messages->last()['timestamp'] ?? $booking->updated_at?->toIso8601String(),
                'status' => $status,
                'unread_count' => (int) $booking->messages()->where('is_read', false)->count(),
                'messages' => $messages,
            ],
        ]);
    }

    private function mapConversationStatus(?string $bookingStatus, ?string $lastContent): string
    {
        $status = strtolower((string) $bookingStatus);
        if (in_array($status, ['cancelled', 'no_show'], true) || $this->isFlaggedContent($lastContent)) {
            return 'Flagged';
        }
        if (in_array($status, ['completed', 'expired', 'completed_pending_review'], true)) {
            return 'Completed';
        }
        return 'Active';
    }

    private function isFlaggedContent(?string $content): bool
    {
        if (!$content) {
            return false;
        }

        $normalized = strtolower($content);
        foreach (['refund', 'complain', 'unacceptable', 'late', 'delay'] as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
