<?php

namespace App\Services;

use App\Models\User;
use App\Models\Resource;
use App\Models\Booking;
use App\Models\Timetable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RecommendationService
{
    /**
     * Get personalized resource recommendations for a user
     *
     * @param User $user
     * @param int $limit
     * @return Collection
     */
    public function getPersonalizedRecommendations(User $user, int $limit = 10): Collection
    {
        Log::info('Frontend hit RecommendationService', ['user_id' => $user->id]);
        try {
            $recommendations = collect();

            // 1. Get recommendations based on different factors
            $bookingHistoryRecommendations = $this->getRecommendationsFromBookingHistory($user, $limit);
            Log::info('Booking history recommendations', ['count' => $bookingHistoryRecommendations->count(), 'data' => $bookingHistoryRecommendations]);
            $departmentRecommendations = $this->getRecommendationsFromDepartment($user, $limit);
            Log::info('Department recommendations', ['count' => $departmentRecommendations->count(), 'data' => $departmentRecommendations]);
            $courseRecommendations = $this->getRecommendationsFromCourseEnrollment($user, $limit);
            Log::info('Course recommendations', ['count' => $courseRecommendations->count(), 'data' => $courseRecommendations]);
            $popularRecommendations = $this->getPopularResourcesRecommendations($limit);
            Log::info('Popular recommendations', ['count' => $popularRecommendations->count(), 'data' => $popularRecommendations]);
            $preferenceRecommendations = $this->getRecommendationsFromUserPreferences($user, $limit);
            Log::info('Preference recommendations', ['count' => $preferenceRecommendations->count(), 'data' => $preferenceRecommendations]);
            $lessPopularRecommendations = $this->getLessPopularButSuitableResources($user, $limit);
            Log::info('Less popular recommendations', ['count' => $lessPopularRecommendations->count(), 'data' => $lessPopularRecommendations]);

            // 2. Combine and weight recommendations
            $recommendations = $this->combineRecommendations([
                $preferenceRecommendations, // highest priority
                $bookingHistoryRecommendations,
                $departmentRecommendations,
                $courseRecommendations,
                $lessPopularRecommendations, // bonus for less popular but suitable
                $popularRecommendations
            ], $limit);
            Log::info('Combined recommendations', ['count' => $recommendations->count(), 'data' => $recommendations]);
            return $recommendations;
        } catch (\Throwable $e) {
            Log::error('Failed to get personalized recommendations: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
                'exception' => $e,
            ]);
            throw new \Exception('Could not retrieve personalized recommendations.');
        }
    }

    /**
     * Get recommendations based on user's booking history
     *
     * @param User $user
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    private function getRecommendationsFromBookingHistory(User $user, int $limit): \Illuminate\Support\Collection
    {
        Log::info('getRecommendationsFromBookingHistory: start', ['user_id' => $user->id, 'limit' => $limit]);
        $pastBookings = $user->bookings()
            ->whereIn('status', [Booking::STATUS_COMPLETED, Booking::STATUS_APPROVED, Booking::STATUS_PENDING])
            ->where('end_time', '<', Carbon::now())
            ->with('resource')
            ->get();
        Log::info('Past bookings', ['count' => $pastBookings->count(), 'data' => $pastBookings]);
        if ($pastBookings->isEmpty()) {
            Log::info('No past bookings found for user', ['user_id' => $user->id]);
            return collect();
        }
        $categoryPreferences = $pastBookings
            ->groupBy(function ($booking) {
                return optional($booking->resource)->category;
            })
            ->filter(function ($bookings, $category) {
                return !is_null($category);
            })
            ->map(function ($bookings, $category) {
                return [
                    'category' => $category,
                    'count' => $bookings->count(),
                    'avg_capacity' => $bookings->avg(function($booking) {
                        return optional($booking->resource)->capacity;
                    })
                ];
            })
            ->sortByDesc('count')
            ->take(3);
        Log::info('Category preferences', ['data' => $categoryPreferences]);
        $categories = $categoryPreferences->pluck('category')->filter()->values();
        Log::info('Categories for recommendations', ['categories' => $categories]);
        if ($categories->isEmpty()) {
            Log::warning('No valid categories found in booking history for recommendations.', ['user_id' => $user->id]);
            return collect();
        }
        $recommendedResources = Resource::whereIn('category', $categories)
            ->where('status', 'available')
            ->whereNotIn('id', $pastBookings->pluck('resource_id'))
            ->orderBy('name')
            ->limit($limit)
            ->get();
        Log::info('Recommended resources from booking history', ['count' => $recommendedResources->count(), 'data' => $recommendedResources]);
        return $recommendedResources->map(function ($resource) use ($categoryPreferences) {
            $category = $categoryPreferences->firstWhere('category', $resource->category);
            return [
                'resource' => $resource,
                'score' => $category ? $category['count'] * 0.8 : 1,
                'reason' => 'Based on your booking history'
            ];
        });
    }

    /**
     * Get recommendations based on user's department (if available)
     *
     * @param User $user
     * @param int $limit
     * @return Collection
     */
    private function getRecommendationsFromDepartment(User $user, int $limit): Collection
    {
        Log::info('getRecommendationsFromDepartment: start', ['user_id' => $user->id, 'limit' => $limit]);
        $departmentCategories = $this->getDepartmentCategories($user->user_type);
        Log::info('Department categories', ['categories' => $departmentCategories]);
        if (empty($departmentCategories)) {
            Log::info('No department categories found for user', ['user_id' => $user->id]);
            return collect();
        }
        $resources = Resource::whereIn('category', $departmentCategories)
            ->where('status', 'available')
            ->orderBy('name')
            ->limit($limit)
            ->get();
        Log::info('Department resources', ['count' => $resources->count(), 'data' => $resources]);
        return $resources->map(function ($resource) {
            return [
                'resource' => $resource,
                'score' => 0.6,
                'reason' => 'Popular in your department'
            ];
        });
    }

    /**
     * Get recommendations based on course enrollment
     *
     * @param User $user
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    private function getRecommendationsFromCourseEnrollment(User $user, int $limit): \Illuminate\Support\Collection
    {
        Log::info('getRecommendationsFromCourseEnrollment: start', ['user_id' => $user->id, 'limit' => $limit]);
        $currentSemester = $this->getCurrentSemester();
        Log::info('Current semester', ['semester' => $currentSemester]);
        $enrolledCourses = $this->getEnrolledCourses($user, $currentSemester);
        Log::info('Enrolled courses', ['courses' => $enrolledCourses]);
        if (empty($enrolledCourses)) {
            Log::info('No enrolled courses found for user', ['user_id' => $user->id]);
            return collect();
        }
        $courseResources = Timetable::whereIn('course_code', $enrolledCourses)
            ->where('semester', $currentSemester)
            ->pluck('room')
            ->unique();
        Log::info('Course resources (rooms)', ['rooms' => $courseResources]);
        $resources = Resource::whereIn('name', $courseResources)
            ->where('status', 'available')
            ->orderBy('name')
            ->limit($limit)
            ->get();
        Log::info('Resources for enrolled courses', ['count' => $resources->count(), 'data' => $resources]);
        return $resources->map(function ($resource) {
            return [
                'resource' => $resource,
                'score' => 0.7,
                'reason' => 'Used by your courses'
            ];
        });
    }

    /**
     * Get popular resources recommendations
     *
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    private function getPopularResourcesRecommendations(int $limit): \Illuminate\Support\Collection
    {
        Log::info('getPopularResourcesRecommendations: start', ['limit' => $limit]);
        $popularResources = Booking::select('resource_id', DB::raw('count(*) as booking_count'))
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->whereIn('status', [Booking::STATUS_COMPLETED, Booking::STATUS_APPROVED])
            ->groupBy('resource_id')
            ->orderByDesc('booking_count')
            ->limit($limit)
            ->pluck('resource_id');
        Log::info('Popular resource IDs', ['ids' => $popularResources]);
        $resources = Resource::whereIn('id', $popularResources)
            ->where('status', 'available')
            ->orderBy('name')
            ->get();
        Log::info('Popular resources', ['count' => $resources->count(), 'data' => $resources]);
        return $resources->map(function ($resource) {
            return [
                'resource' => $resource,
                'score' => 0.5,
                'reason' => 'Popular choice'
            ];
        });
    }

    /**
     * Combine and weight different recommendation sources
     *
     * @param array $recommendationSets
     * @param int $limit
     * @return Collection
     */
    private function combineRecommendations(array $recommendationSets, int $limit): Collection
    {
        Log::info('combineRecommendations: start', ['sets_count' => count($recommendationSets), 'limit' => $limit]);
        $combined = collect();
        $resourceScores = [];
        foreach ($recommendationSets as $recommendations) {
            foreach ($recommendations as $recommendation) {
                $resourceId = $recommendation['resource']->id;
                if (!isset($resourceScores[$resourceId])) {
                    $resourceScores[$resourceId] = [
                        'resource' => $recommendation['resource'],
                        'score' => 0,
                        'reasons' => []
                    ];
                }
                $resourceScores[$resourceId]['score'] += $recommendation['score'];
                $resourceScores[$resourceId]['reasons'][] = $recommendation['reason'];
            }
        }
        Log::info('Resource scores before sorting', ['resourceScores' => $resourceScores]);
        $sortedRecommendations = collect($resourceScores)
            ->sortByDesc('score')
            ->take($limit);
        Log::info('Sorted recommendations', ['count' => $sortedRecommendations->count(), 'data' => $sortedRecommendations]);
        return $sortedRecommendations->map(function ($item) {
            return [
                'resource' => $item['resource'],
                'score' => $item['score'],
                'reasons' => array_unique($item['reasons'])
            ];
        });
    }

    /**
     * Get department-specific resource categories
     *
     * @param string $userType
     * @return array
     */
    private function getDepartmentCategories(string $userType): array
    {
        $categories = [
            'student' => ['classroom', 'laboratory', 'computer_lab', 'study_room'],
            'staff' => ['meeting_room', 'conference_room', 'office', 'classroom'],
            'admin' => ['meeting_room', 'conference_room', 'office', 'classroom']
        ];

        return $categories[$userType] ?? [];
    }

    /**
     * Get current semester
     *
     * @return string
     */
    private function getCurrentSemester(): string
    {
        $month = Carbon::now()->month;
        
        if ($month >= 1 && $month <= 5) {
            return 'Spring';
        } elseif ($month >= 6 && $month <= 8) {
            return 'Summer';
        } else {
            return 'Fall';
        }
    }

    /**
     * Get enrolled courses for a user
     *
     * @param User $user
     * @param string $semester
     * @return array
     */
    private function getEnrolledCourses(User $user, string $semester): array
    {
        // This is a simplified implementation
        // In a real system, you'd have a proper enrollment table
        if ($user->user_type === 'student') {
            // Return some sample course codes
            return ['CS101', 'MATH201', 'ENG101'];
        }
        
        return [];
    }

    /**
     * Get time-based recommendations (resources available at preferred times)
     *
     * @param User $user
     * @param Carbon $preferredTime
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function getTimeBasedRecommendations(User $user, Carbon $preferredTime, int $limit = 5): \Illuminate\Support\Collection
    {
        try {
            // Get user's preferred booking times from history
            $preferredHours = $user->bookings()
                ->whereIn('status', [Booking::STATUS_COMPLETED, Booking::STATUS_APPROVED])
                ->get()
                ->groupBy(function ($booking) {
                    return $booking->start_time->hour;
                })
                ->map->count()
                ->sortByDesc(function ($count) {
                    return $count;
                })
                ->take(3)
                ->keys();
            // Find available resources at preferred times
            $availableResources = Resource::where('status', 'available')
                ->whereDoesntHave('bookings', function ($query) use ($preferredTime) {
                    $query->where('start_time', '<=', $preferredTime)
                          ->where('end_time', '>', $preferredTime)
                          ->whereIn('status', [Booking::STATUS_APPROVED, Booking::STATUS_PENDING]);
                })
                ->orderBy('name')
                ->limit($limit)
                ->get();
            return $availableResources->map(function ($resource) {
                return [
                    'resource' => $resource,
                    'score' => 0.8,
                    'reason' => 'Available at your preferred time'
                ];
            });
        } catch (\Throwable $e) {
            \Log::error('Failed to get time-based recommendations: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'preferred_time' => $preferredTime,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Could not retrieve time-based recommendations.');
        }
    }

    /**
     * Get recommendations based on user's stated preferences (if available)
     *
     * @param User $user
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    private function getRecommendationsFromUserPreferences(User $user, int $limit): \Illuminate\Support\Collection
    {
        Log::info('getRecommendationsFromUserPreferences: start', ['user_id' => $user->id, 'limit' => $limit]);
        $preferences = $user->preferences ?? null;
        Log::info('User preferences', ['preferences' => $preferences]);
        if (!$preferences) {
            Log::info('No preferences found for user', ['user_id' => $user->id]);
            return collect();
        }
        $preferredCategories = $preferences['categories'] ?? [];
        $preferredTimes = $preferences['times'] ?? [];
        $preferredCapacity = $preferences['capacity'] ?? null;
        Log::info('Preferred categories', ['categories' => $preferredCategories]);
        Log::info('Preferred times', ['times' => $preferredTimes]);
        Log::info('Preferred capacity', ['capacity' => $preferredCapacity]);
        $query = Resource::query()->where('status', 'available');
        if (!empty($preferredCategories)) {
            $query->whereIn('category', $preferredCategories);
        }
        if ($preferredCapacity) {
            $query->where('capacity', '>=', $preferredCapacity);
        }
        $resources = $query->orderBy('name')->limit($limit)->get();
        Log::info('Resources matching preferences', ['count' => $resources->count(), 'data' => $resources]);
        return $resources->map(function ($resource) {
            return [
                'resource' => $resource,
                'score' => 1.0, // highest weight for stated preferences
                'reason' => 'Matches your stated preferences'
            ];
        });
    }

    /**
     * Promote less popular but suitable resources that match user preferences
     *
     * @param User $user
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    private function getLessPopularButSuitableResources(User $user, int $limit): \Illuminate\Support\Collection
    {
        Log::info('getLessPopularButSuitableResources: start', ['user_id' => $user->id, 'limit' => $limit]);
        $preferences = $user->preferences ?? null;
        Log::info('User preferences', ['preferences' => $preferences]);
        if (!$preferences) {
            Log::info('No preferences found for user', ['user_id' => $user->id]);
            return collect();
        }
        $preferredCategories = $preferences['categories'] ?? [];
        $preferredCapacity = $preferences['capacity'] ?? null;
        Log::info('Preferred categories', ['categories' => $preferredCategories]);
        Log::info('Preferred capacity', ['capacity' => $preferredCapacity]);
        $popularResourceIds = Booking::select('resource_id', DB::raw('count(*) as booking_count'))
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->whereIn('status', [Booking::STATUS_COMPLETED, Booking::STATUS_APPROVED])
            ->groupBy('resource_id')
            ->orderByDesc('booking_count')
            ->limit(10)
            ->pluck('resource_id')
            ->toArray();
        Log::info('Popular resource IDs (for exclusion)', ['ids' => $popularResourceIds]);
        $query = Resource::query()->where('status', 'available');
        if (!empty($preferredCategories)) {
            $query->whereIn('category', $preferredCategories);
        }
        if ($preferredCapacity) {
            $query->where('capacity', '>=', $preferredCapacity);
        }
        $query->whereNotIn('id', $popularResourceIds);
        $resources = $query->orderBy('name')->limit($limit)->get();
        Log::info('Less popular but suitable resources', ['count' => $resources->count(), 'data' => $resources]);
        return $resources->map(function ($resource) {
            return [
                'resource' => $resource,
                'score' => 0.9, // bonus for being less popular but suitable
                'reason' => 'Suitable but less popular resource'
            ];
        });
    }

    /**
     * Get general resource recommendations for guests or all users
     *
     * @param int $limit
     * @return Collection
     */
    public function getGeneralRecommendations(int $limit = 10): Collection
    {
        // For guests, just return popular resources
        return $this->getPopularResourcesRecommendations($limit);
    }
} 