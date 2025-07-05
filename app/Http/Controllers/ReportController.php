<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Get the resource utilization report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getResourceUtilization(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Authorization: Only admins can access reports
        if (!$user || $user->user_type !== 'admin') {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized to access reports."
            ], 403); // Use 403 Forbidden for authorization issues
        }

        // Validate incoming request for date parameters
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $report = $this->reportService->getResourceUtilizationReport($startDate, $endDate);

        if ($report['success']) {
            return response()->json([
                "success" => true,
                "message" => "Resource utilization report generated successfully.",
                "report" => $report['report'],
                "period" => [
                    "start_date" => $startDate,
                    "end_date" =>$endDate,
                ]
            ], 200);
        } else {
            return response()->json([
                "success" => false,
                "message" => $report['message']
            ], 500); 
        }
    }

    /**
     * Get the booking summary report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getBookingSummary(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Authorization: Only admins can access reports
        if (!$user || $user->user_type !== 'admin') {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized to access reports."
            ], 403);
        }

        // Validate incoming request parameters
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'resource_type' => 'nullable|string|in:classrooms,ict_labs,science_labs,sports,cars,auditorium',
            'resource_id' => 'nullable|integer|exists:resources,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'user_type' => 'nullable|string|in:staff,student,admin',
        ]);

        // Prepare filters
        $filters = $request->only([
            'start_date',
            'end_date', 
            'resource_type',
            'resource_id',
            'user_id',
            'user_type'
        ]);

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $report = $this->reportService->getBookingSummaryReport($filters);
        Log::info("booking summary " . json_encode($report));

        if ($report['success']) {
            return response()->json([
                "success" => true,
                "message" => "Booking summary report generated successfully.",
                "report" => $report['report'],
                "period" => $report['period'],
                "filters_applied" => $report['filters_applied'] ?? []
            ], 200);
        } else {
            return response()->json([
                "success" => false,
                "message" => $report['message']
            ], 500);
        }
    }

    /**
     * Get the upcoming bookings report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUpcomingBookings(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Authorization: Only admins can access reports
        if (!$user || $user->user_type !== 'admin') {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized to access reports."
            ], 403);
        }

        // Validate incoming request parameters
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'resource_type' => 'nullable|string',
            'resource_id' => 'nullable|integer|exists:resources,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'user_type' => 'nullable|string|in:staff,student,admin',
            'status' => 'nullable|string|in:approved,pending,in_use',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        // Prepare filters
        $filters = $request->only([
            'start_date',
            'end_date', 
            'resource_type',
            'resource_id',
            'user_id',
            'user_type',
            'status',
            'limit'
        ]);

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $report = $this->reportService->getUpcomingBookingsReport($filters);

        if ($report['success']) {
            return response()->json([
                "success" => true,
                "message" => "Upcoming bookings report generated successfully.",
                "report" => $report['report'],
                "period" => $report['period'],
                "filters_applied" => $report['filters_applied'] ?? [],
                "total_bookings" => $report['total_bookings'] ?? 0
            ], 200);
        } else {
            return response()->json([
                "success" => false,
                "message" => $report['message']
            ], 500);
        }
    }
}