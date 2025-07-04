<?php

namespace App\Http\Controllers;

use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RecommendationController extends Controller
{
    protected $recommendationService;

    public function __construct(RecommendationService $recommendationService)
    {
        $this->recommendationService = $recommendationService;
    }

    /**
     * Get personalized resource recommendations for the authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getRecommendations(Request $request): JsonResponse
    {
        $user = Auth::user();
        $limit = $request->query('limit', 10);
        try {
            if ($user) {
                $recommendations = $this->recommendationService->getPersonalizedRecommendations($user, $limit);
            } else {
                $recommendations = $this->recommendationService->getGeneralRecommendations($limit);
            }
            Log::info('Recommendations: ' . json_encode($recommendations));
            return response()->json([
                "success" => true,
                "recommendations" => $recommendations->map(function ($item) {
                    return [
                        'resource' => $item['resource'],
                        'score' => round($item['score'], 2),
                        'reasons' => $item['reasons'] ?? [$item['reason'] ?? 'Popular choice']
                    ];
                })->values()
            ]);
        } catch (\Exception $e) {
            Log::error('RecommendationController@getRecommendations failed: ' . $e->getMessage());
            return response()->json([
                "success" => false,
                "message" => "An unexpected error occurred while fetching recommendations."
            ], 500);
        }
    }

    /**
     * Get time-based resource recommendations for a specific time
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTimeBasedRecommendations(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                "success" => false,
                "message" => "Unauthenticated."
            ], 401);
        }

        $request->validate([
            'preferred_time' => 'required|date',
            'limit' => 'integer|min:1|max:20'
        ]);

        $preferredTime = Carbon::parse($request->input('preferred_time'));
        $limit = $request->input('limit', 5);

        try {
            $recommendations = $this->recommendationService->getTimeBasedRecommendations($user, $preferredTime, $limit);
            return response()->json([
                "success" => true,
                "recommendations" => $recommendations->map(function ($item) {
                    return [
                        'resource' => $item['resource'],
                        'score' => round($item['score'], 2),
                        'reason' => $item['reason']
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('RecommendationController@getTimeBasedRecommendations failed: ' . $e->getMessage());
            return response()->json([
                "success" => false,
                "message" => "An unexpected error occurred while fetching time-based recommendations."
            ], 500);
        }
    }

    /**
     * Get user's booking preferences and patterns
     *
     * @return JsonResponse
     */
    public function getUserPreferences(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                "success" => false,
                "message" => "Unauthenticated."
            ], 401);
        }

        try {
            $pastBookings = $user->bookings()
                ->whereIn('status', [\App\Models\Booking::STATUS_COMPLETED, \App\Models\Booking::STATUS_APPROVED])
                ->where('end_time', '<', Carbon::now())
                ->with('resource')
                ->get();

            if ($pastBookings->isEmpty()) {
                return response()->json([
                    "success" => true,
                    "preferences" => [
                        "message" => "No booking history available to analyze preferences.",
                        "favorite_categories" => [],
                        "preferred_times" => [],
                        "average_capacity" => null
                    ]
                ]);
            }

            $favoriteCategories = $pastBookings
                ->groupBy('resource.category')
                ->map(function ($bookings) use ($pastBookings) {
                    return [
                        'category' => $bookings->first()->resource->category,
                        'count' => $bookings->count(),
                        'percentage' => round(($bookings->count() / $pastBookings->count()) * 100, 1)
                    ];
                })
                ->sortByDesc('count')
                ->take(5)
                ->values();

            $preferredTimes = $pastBookings
                ->groupBy(function ($booking) {
                    return $booking->start_time->format('H:i');
                })
                ->map(function ($bookings) {
                    return [
                        'time' => $bookings->first()->start_time->format('H:i'),
                        'count' => $bookings->count()
                    ];
                })
                ->sortByDesc('count')
                ->take(5)
                ->values();

            $averageCapacity = round($pastBookings->avg('resource.capacity'), 0);

            return response()->json([
                "success" => true,
                "preferences" => [
                    "favorite_categories" => $favoriteCategories,
                    "preferred_times" => $preferredTimes,
                    "average_capacity" => $averageCapacity,
                    "total_bookings" => $pastBookings->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('RecommendationController@getUserPreferences failed: ' . $e->getMessage());
            return response()->json([
                "success" => false,
                "message" => "An unexpected error occurred while fetching user preferences."
            ], 500);
        }
    }
} 