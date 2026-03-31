<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportSession;
use App\Models\SupportMessage;
use App\Events\SupportMessageSent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SupportChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $status = $request->query('status', 'open');

        $sessions = SupportSession::with(['user', 'admin', 'messages' => function ($query) {
            $query->latest();
        }])
        ->when($status !== 'all', function ($query) use ($status) {
            return $query->where('status', $status);
        })
        ->orderBy('updated_at', 'desc')
        ->get();

        return response()->json([
            'sessions' => $sessions->map(function ($session) {
                return [
                    'id' => $session->id,
                    'user_id' => $session->user_id,
                    'user_name' => $session->user->first_name . ' ' . $session->user->last_name,
                    'user_avatar' => $session->user->profile_photo_url,
                    'user_role' => $session->user->role, // Client or Therapist
                    'user_avatar' => $session->user->profile_photo_url,
                    'admin_id' => $session->admin_id,
                    'admin_name' => $session->admin ? ($session->admin->first_name . ' ' . $session->admin->last_name) : 'Admin Support',
                    'admin_avatar' => $session->admin ? $session->admin->profile_photo_url : null,
                    'subject' => $session->subject,
                    'status' => $session->status,
                    'last_message' => $session->messages->first()->content ?? null,
                    'updated_at' => $session->updated_at->toIso8601String(),
                ];
            })
        ]);
    }

    public function getSession(Request $request): JsonResponse
    {
        $user = $request->user();

        // Admin might be asking for a specific session by user_id
        if ($user->role === 'admin' && $request->has('user_id')) {
            $session = SupportSession::where('user_id', $request->user_id)
                ->where('status', 'open')
                ->first();
            
            if (!$session) {
                return response()->json(['message' => 'No active session found for this user'], 404);
            }

            return response()->json(['session' => $session]);
        }

        // Standard client flow: get or create their own active session
        $session = SupportSession::firstOrCreate(
            ['user_id' => $user->id, 'status' => 'open'],
            ['subject' => 'General Support']
        );

        return response()->json(['session' => $session]);
    }

    public function getMessages(int $sessionId, Request $request): JsonResponse
    {
        $user = $request->user();
        $session = SupportSession::findOrFail($sessionId);

        // Security check
        if ($user->role !== 'admin' && $user->id !== $session->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $messages = SupportMessage::with('sender')
            ->where('support_session_id', $sessionId)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'messages' => $messages->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'content' => $msg->content,
                    'sender_id' => $msg->sender_id,
                    'sender_name' => $msg->sender->first_name . ' ' . $msg->sender->last_name,
                    'sender_avatar' => $msg->sender->profile_photo_url,
                    'sender_role' => $msg->sender->role,
                    'created_at' => $msg->created_at->toIso8601String(),
                ];
            })
        ]);
    }

    public function sendMessage(int $sessionId, Request $request): JsonResponse
    {
        $user = $request->user();
        $session = SupportSession::findOrFail($sessionId);

        // Security check
        if ($user->role !== 'admin' && $user->id !== $session->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $message = SupportMessage::create([
            'support_session_id' => $sessionId,
            'sender_id' => $user->id,
            'content' => $request->content,
        ]);

        // If admin is sending, mark them as the admin for this session if not already set
        if ($user->role === 'admin' && !$session->admin_id) {
            $session->update(['admin_id' => $user->id]);
        }

        try {
            broadcast(new SupportMessageSent($message))->toOthers();
        } catch (\Exception $e) {
            \Log::error("Broadcasting failed: " . $e->getMessage());
        }

        return response()->json([
            'message' => [
                'id' => $message->id,
                'content' => $message->content,
                'sender_id' => $message->sender_id,
                'sender_name' => $user->first_name . ' ' . $user->last_name,
                'sender_avatar' => $user->profile_photo_url,
                'sender_role' => $user->role,
                'created_at' => $message->created_at->toIso8601String(),
            ]
        ]);
    }

    public function closeSession(int $sessionId, Request $request): JsonResponse
    {
        $user = $request->user();
        $session = SupportSession::findOrFail($sessionId);

        if ($user->role !== 'admin' && $user->id !== $session->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $session->update(['status' => 'closed']);

        return response()->json(['message' => 'Session closed']);
    }

    public function reopenSession(int $sessionId, Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $session = SupportSession::findOrFail($sessionId);
        $session->update(['status' => 'open']);

        return response()->json(['message' => 'Session re-opened', 'session' => $session]);
    }
}
