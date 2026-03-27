<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('therapist.{providerId}', function ($user, $providerId) {
    \Illuminate\Support\Facades\Log::info('Channel Auth Debug', ['user_id' => $user->id, 'provider_id' => $providerId]);
    $exists = $user->providers()->where('id', $providerId)->exists();
    \Illuminate\Support\Facades\Log::info('Channel Auth Result', ['authorized' => $exists]);
    return $exists;
});

Broadcast::channel('booking.{bookingId}', function ($user, $bookingId) {
    $booking = \App\Models\Booking::find($bookingId);
    if (!$booking) return false;
    
    // Allow customer or assigned therapist
    if ($user->id === $booking->customer_id) return true;
    if ($user->role === 'therapist') {
        $provider = $user->providers()->where('type', 'therapist')->first();
        return $provider && $booking->provider_id === $provider->id;
    }
    return false;
});

Broadcast::channel('support.session.{sessionId}', function ($user, $sessionId) {
    // Allow the user who owns this support session
    $session = \App\Models\SupportSession::find($sessionId);
    if (!$session) return false;
    return $user->id === $session->user_id || $user->role === 'admin';
});
