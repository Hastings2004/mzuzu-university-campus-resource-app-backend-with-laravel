<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RecommendationService;
use Illuminate\Support\Facades\Log;

class ResourceRecommendationController extends Controller
{
    /**
     * Return personalized resource recommendations for the authenticated user.
     */
    public function index(Request $request, RecommendationService $service)
    {
        $user = $request->user();
        $recommendations = $service->getPersonalizedRecommendations($user, 10);
        Log::info('Recommendations: ' . json_encode($recommendations));
        return response()->json($recommendations);
    }
} 