<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\DashboardService; // Import the new service
use App\Exceptions\DashboardException; // Import the custom exception

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        //$this->middleware('auth:sanctum'); // Apply auth middleware to all methods
        $this->dashboardService = $dashboardService;
    }

    /**
     * Display the dashboard data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Check if user is admin
        $user = Auth::user();
        if (!$user || $user->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access. Only admins can view the dashboard.'
            ], 403);
        }

        $period = $request->query('period', 'month');
        $date = $request->query('date', null);

        try {
            $dashboardData = $this->dashboardService->getAllDashboardData($period, $date);

            return response()->json($dashboardData);
        } catch (DashboardException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500); // Use specific code or default to 500
        } catch (\Exception $e) {
            Log::error('DashboardController@index failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while fetching dashboard data.'
            ], 500);
        }
    }

    /**
     * Debug method to test monthly booking trends and resource utilization.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function debug(Request $request): JsonResponse
    {
        // Check if user is admin
        $user = Auth::user();
        if (!$user || $user->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access. Only admins can view the dashboard.'
            ], 403);
        }

        try {
            // Test basic booking count
            $totalBookings = \App\Models\Booking::count();
            
            // Test current year bookings
            $currentYear = date('Y');
            $currentYearBookings = \App\Models\Booking::whereYear('start_time', $currentYear)->count();
            
            // Test the new implementation
            $monthlyTrends = $this->dashboardService->getMonthlyBookingTrends();
            $resourceUtilization = $this->dashboardService->getResourceUtilizationMonthly();
            
            // Get sample booking dates
            $sampleBookings = \App\Models\Booking::select('start_time', 'end_time')
                ->limit(5)
                ->get();

            return response()->json([
                'debug_info' => [
                    'current_year' => $currentYear,
                    'total_bookings' => $totalBookings,
                    'current_year_bookings' => $currentYearBookings,
                    'monthly_trends' => $monthlyTrends,
                    'resource_utilization' => $resourceUtilization,
                    'sample_bookings' => $sampleBookings,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Debug failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Get bookings by resource type (category) for bar chart.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bookingsByResourceType(Request $request): JsonResponse
    {
        $user = \Auth::user();
        if (!$user || $user->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access. Only admins can view this data.'
            ], 403);
        }

        $period = $request->query('period', 'month');
        $date = $request->query('date', null);

        try {
            $data = $this->dashboardService->getBookingsByResourceType($period, $date);
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (DashboardException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        } catch (\Exception $e) {
            \Log::error('DashboardController@bookingsByResourceType failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while fetching bookings by resource type.'
            ], 500);
        }
    }

    /**
     * Get the distribution of booking durations (in hours) for histogram/box plot.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bookingDurationDistribution(Request $request): JsonResponse
    {
        $user = \Auth::user();
        if (!$user || $user->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access. Only admins can view this data.'
            ], 403);
        }

        $period = $request->query('period', 'month');
        $date = $request->query('date', null);

        try {
            $data = $this->dashboardService->getBookingDurationDistribution($period, $date);
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (DashboardException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        } catch (\Exception $e) {
            \Log::error('DashboardController@bookingDurationDistribution failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while fetching booking duration distribution.'
            ], 500);
        }
    }

    /**
     * Get the current cancellation rate as a percentage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function cancellationRate(Request $request): JsonResponse
    {
        $user = \Auth::user();
        if (!$user || $user->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access. Only admins can view this data.'
            ], 403);
        }
        $period = $request->query('period', 'month');
        $date = $request->query('date', null);
        try {
            $rate = $this->dashboardService->getCancellationRate($period, $date);
            return response()->json([
                'success' => true,
                'cancellation_rate' => $rate
            ]);
        } catch (DashboardException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        } catch (\Exception $e) {
            \Log::error('DashboardController@cancellationRate failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while fetching cancellation rate.'
            ], 500);
        }
    }

    /**
     * Get monthly cancellation rate trends for the current year.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function cancellationTrends(Request $request): JsonResponse
    {
        $user = \Auth::user();
        if (!$user || $user->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access. Only admins can view this data.'
            ], 403);
        }
        $period = $request->query('period', 'month');
        $date = $request->query('date', null);
        try {
            $trends = $this->dashboardService->getCancellationTrends($period, $date);
            return response()->json([
                'success' => true,
                'trends' => $trends
            ]);
        } catch (DashboardException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        } catch (\Exception $e) {
            \Log::error('DashboardController@cancellationTrends failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while fetching cancellation trends.'
            ], 500);
        }
    }
}
