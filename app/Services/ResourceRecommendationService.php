<?php

namespace App\Services;

use App\Models\User;
use App\Models\Resource;
use App\Models\Booking;

class ResourceRecommendationService
{
    /**
     * Recommend resources for a user based on booking history, preferences, and less popular resources.
     *
     * @param User $user
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function recommendForUser(User $user, $limit = 5)
    {
        // 1. Get user's booking history (resource_ids and categories)
        $bookedResourceIds = $user->bookings()->pluck('resource_id')->toArray();
        $bookedCategories = Resource::whereIn('id', $bookedResourceIds)->pluck('category')->toArray();

        // 2. Get user preferences (array of preferred categories, etc.)
        $preferences = $user->preferences ?? [];
        $preferredCategories = $preferences['categories'] ?? [];

        // 3. Build a query for resources
        $query = Resource::query();

        // Prefer resources in user's preferred categories
        if (!empty($preferredCategories)) {
            $query->whereIn('category', $preferredCategories);
        } elseif (!empty($bookedCategories)) {
            // Otherwise, prefer categories the user has booked before
            $query->whereIn('category', $bookedCategories);
        }

        // Exclude resources the user has already booked (optional)
        if (!empty($bookedResourceIds)) {
            $query->whereNotIn('id', $bookedResourceIds);
        }

        // Promote less popular resources by ordering by booking count ascending
        $query->withCount('bookings')->orderBy('bookings_count', 'asc');

        // Fallback: if not enough, fill with any resource
        $resources = $query->limit($limit)->get();
        if ($resources->count() < $limit) {
            $additional = Resource::whereNotIn('id', $resources->pluck('id'))
                ->orderBy('bookings_count', 'asc')
                ->withCount('bookings')
                ->limit($limit - $resources->count())
                ->get();
            $resources = $resources->concat($additional);
        }

        return $resources->take($limit)->values();
    }
} 