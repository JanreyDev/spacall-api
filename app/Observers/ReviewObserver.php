<?php

namespace App\Observers;

use App\Models\Review;
use App\Models\Provider;

class ReviewObserver
{
    /**
     * Handle the Review "created" event.
     */
    public function created(Review $review): void
    {
        $this->updateProviderStats($review->provider_id);
    }

    /**
     * Handle the Review "updated" event.
     */
    public function updated(Review $review): void
    {
        $this->updateProviderStats($review->provider_id);
    }

    /**
     * Handle the Review "deleted" event.
     */
    public function deleted(Review $review): void
    {
        $this->updateProviderStats($review->provider_id);
    }

    /**
     * Recalculate and update provider stats.
     */
    protected function updateProviderStats($providerId): void
    {
        if (!$providerId) return;

        $provider = Provider::find($providerId);
        if (!$provider) return;

        $stats = Review::where('provider_id', $providerId)
            ->selectRaw('COUNT(*) as total_reviews, AVG(rating) as average_rating')
            ->first();

        $provider->update([
            'total_reviews' => $stats->total_reviews ?? 0,
            'average_rating' => round($stats->average_rating ?? 0, 2),
        ]);
    }
}
