<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\Tier;
use App\Models\TherapistStat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VipService
{
    /**
     * Update therapist statistics.
     */
    public function updateStats(Provider $provider, string $type, int $increment = 1)
    {
        if ($provider->type !== 'therapist') {
            return;
        }

        $stats = $provider->therapistStat()->firstOrCreate(
            ['provider_id' => $provider->id],
            ['total_online_minutes' => 0, 'total_extensions' => 0, 'total_bookings' => 0]
        );

        if ($type === 'bookings') {
            $stats->increment('total_bookings', $increment);
        } elseif ($type === 'extensions') {
            $stats->increment('total_extensions', $increment);
        }

        $this->checkEligibility($provider);
    }

    /**
     * Update online minutes when therapist goes offline.
     */
    public function updateOnlineMinutes(Provider $provider)
    {
        if ($provider->type !== 'therapist') {
            return;
        }

        $stats = $provider->therapistStat()->firstOrCreate(
            ['provider_id' => $provider->id],
            ['total_online_minutes' => 0, 'total_extensions' => 0, 'total_bookings' => 0]
        );

        if ($stats->last_online_at) {
            $minutes = now()->diffInMinutes($stats->last_online_at);
            if ($minutes > 0) {
                $stats->increment('total_online_minutes', $minutes);
            }
        }

        $stats->update(['last_online_at' => null]);

        $this->checkEligibility($provider);
    }

    /**
     * Start tracking online time.
     */
    public function startOnlineSession(Provider $provider)
    {
        if ($provider->type !== 'therapist') {
            return;
        }

        $stats = $provider->therapistStat()->firstOrCreate(
            ['provider_id' => $provider->id],
            ['total_online_minutes' => 0]
        );

        if (!$stats->last_online_at) {
            $stats->update(['last_online_at' => now()]);
        }
    }

    /**
     * Check and update therapist tier eligibility.
     */
    public function checkEligibility(Provider $provider)
    {
        $stats = $provider->therapistStat;
        if (!$stats)
            return;

        // Find highest level available based on requirements
        $eligibleTier = Tier::where('online_minutes_required', '<=', $stats->total_online_minutes)
            ->where('extensions_required', '<=', $stats->total_extensions)
            ->where('bookings_required', '<=', $stats->total_bookings)
            ->orderBy('level', 'desc')
            ->first();

        if ($eligibleTier) {
            $provider->update(['current_tier_id' => $eligibleTier->id]);
        }
    }

    /**
     * Re-evaluate all therapists (e.g., after tier requirements change).
     */
    public function reevaluateAll()
    {
        Provider::where('type', 'therapist')->chunk(100, function ($providers) {
            foreach ($providers as $provider) {
                $this->checkEligibility($provider);
            }
        });
    }
}
