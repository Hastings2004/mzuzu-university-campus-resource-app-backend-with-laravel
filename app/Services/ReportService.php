<?php

namespace App\Services;

use App\Models\Resource;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;

class ReportService
{
    private const BOOKING_STATUSES = [
        'approved',
        'in_use', 
        'completed'
    ];

    private const DEFAULT_DATE_FORMAT = 'Y-m-d';

    /**
     * Generate a comprehensive resource utilization report for a specified date range.
     *
     * @param string|null $startDate Start date in Y-m-d format
     * @param string|null $endDate End date in Y-m-d format
     * @return array Report data with success status and utilization metrics
     */
    public function getResourceUtilizationReport(?string $startDate = null, ?string $endDate = null): array
    {
        try {
            $this->logReportRequest($startDate, $endDate);
            
            $dateRange = $this->prepareDateRange($startDate, $endDate);
            
            if (!$this->isValidDateRange($dateRange)) {
                return $this->createErrorResponse('Start date cannot be after end date.', $dateRange);
            }

            $reportPeriod = $this->calculateReportPeriod($dateRange);
            $resources = Resource::all();
            $reportData = $this->generateReportData($resources, $dateRange, $reportPeriod);

            return $this->createSuccessResponse($reportData, $dateRange);

        } catch (\Exception $e) {
            Log::error("Error generating resource utilization report: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'start_date' => $startDate ?? 'null',
                'end_date' => $endDate ?? 'null'
            ]);
            
            return $this->createErrorResponse(
                'An error occurred while generating the report. Please try again.',
                $this->prepareDateRange($startDate, $endDate)
            );
        }
    }

    /**
     * Log the initial report request for debugging purposes.
     */
    private function logReportRequest(?string $startDate, ?string $endDate): void
    {
        Log::debug("Report request received", [
            'start_date_param' => $startDate,
            'end_date_param' => $endDate
        ]);
    }

    /**
     * Prepare and normalize the date range for the report.
     */
    private function prepareDateRange(?string $startDate, ?string $endDate): array
    {
        $start = $startDate 
            ? Carbon::parse($startDate)->startOfDay()
            : Carbon::now()->startOfMonth()->startOfDay();
            
        $end = $endDate 
            ? Carbon::parse($endDate)->endOfDay()
            : Carbon::now()->endOfMonth()->endOfDay();

        Log::debug("Date range prepared", [
            'start_formatted' => $start->toDateTimeString(),
            'end_formatted' => $end->toDateTimeString()
        ]);

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Validate that the date range is logical.
     */
    private function isValidDateRange(array $dateRange): bool
    {
        return $dateRange['start']->toDateString() <= $dateRange['end']->toDateString();
    }

    /**
     * Calculate the total duration of the report period.
     */
    private function calculateReportPeriod(array $dateRange): array
    {
        // Calculate duration in minutes between start and end dates
        $durationMinutes = $dateRange['end']->diffInMinutes($dateRange['start'], false);
        
        // Ensure we get a positive value
        $durationMinutes = abs($durationMinutes);
        $durationHours = $durationMinutes / 60;

        // Handle edge case of zero duration
        if ($durationMinutes === 0) {
            $durationMinutes = 1;
            $durationHours = 1/60;
        }

        Log::debug("Report period calculated", [
            'duration_minutes' => $durationMinutes,
            'duration_hours' => $durationHours,
            'start_date' => $dateRange['start']->toDateTimeString(),
            'end_date' => $dateRange['end']->toDateTimeString()
        ]);

        return [
            'minutes' => $durationMinutes,
            'hours' => $durationHours
        ];
    }

    /**
     * Generate utilization data for all resources.
     */
    private function generateReportData(\Illuminate\Database\Eloquent\Collection $resources, array $dateRange, array $reportPeriod): array
    {
        $reportData = [];

        foreach ($resources as $resource) {
            $utilizationData = $this->calculateResourceUtilization(
                $resource, 
                $dateRange, 
                $reportPeriod
            );
            
            $reportData[] = $utilizationData;
        }

        return $reportData;
    }

    /**
     * Calculate utilization metrics for a single resource.
     */
    private function calculateResourceUtilization(Resource $resource, array $dateRange, array $reportPeriod): array
    {
        $bookings = $this->getRelevantBookings($resource->id, $dateRange);
        $totalBookedMinutes = $this->calculateTotalBookedTime($bookings, $dateRange);
        $totalBookedHours = $totalBookedMinutes / 60;
        
        // Ensure booked hours is never negative
        $totalBookedHours = max(0, $totalBookedHours);
        
        $utilizationPercentage = $this->calculateUtilizationPercentage(
            $totalBookedHours, 
            $reportPeriod['hours']
        );

        Log::debug("Resource utilization calculated", [
            'resource_id' => $resource->id,
            'resource_name' => $resource->name,
            'total_booked_minutes' => $totalBookedMinutes,
            'total_booked_hours' => $totalBookedHours,
            'total_available_hours' => $reportPeriod['hours'],
            'utilization_percentage' => $utilizationPercentage,
            'booking_count' => $bookings->count(),
        ]);

        return [
            'resource_id' => $resource->id,
            'resource_name' => $resource->name ?? 'Unknown Resource',
            'total_booked_hours' => round($totalBookedHours, 2),
            'total_available_hours_in_period' => round($reportPeriod['hours'], 2),
            'utilization_percentage' => $utilizationPercentage,
            'booking_count' => $bookings->count(),
        ];
    }

    /**
     * Fetch bookings that overlap with the report period.
     */
    private function getRelevantBookings(int $resourceId, array $dateRange): \Illuminate\Database\Eloquent\Collection
    {
        return Booking::where('resource_id', $resourceId)
            ->where(function (Builder $query) use ($dateRange) {
                $query->whereBetween('start_time', [$dateRange['start'], $dateRange['end']])
                      ->orWhereBetween('end_time', [$dateRange['start'], $dateRange['end']])
                      ->orWhere(function (Builder $query) use ($dateRange) {
                          $query->where('start_time', '<=', $dateRange['start'])
                                ->where('end_time', '>=', $dateRange['end']);
                      });
            })
            ->whereIn('status', self::BOOKING_STATUSES)
            ->get();
    }

    /**
     * Calculate total booked time in minutes for all bookings.
     */
    private function calculateTotalBookedTime(\Illuminate\Database\Eloquent\Collection $bookings, array $dateRange): int
    {
        $totalMinutes = 0;

        Log::debug("Starting total booked time calculation", [
            'booking_count' => $bookings->count(),
            'date_range_start' => $dateRange['start']->toDateTimeString(),
            'date_range_end' => $dateRange['end']->toDateTimeString(),
        ]);

        foreach ($bookings as $booking) {
            if (!$this->isValidBooking($booking)) {
                continue;
            }

            $bookingStart = $this->normalizeDateTime($booking->start_time);
            $bookingEnd = $this->normalizeDateTime($booking->end_time);

            if ($bookingEnd->lessThanOrEqualTo($bookingStart)) {
                $this->logInvalidBooking($booking, $bookingStart, $bookingEnd);
                continue;
            }

            $overlapMinutes = $this->calculateBookingOverlap(
                $bookingStart, 
                $bookingEnd, 
                $dateRange
            );
            
            $totalMinutes += $overlapMinutes;
            
            Log::debug("Booking processed", [
                'booking_id' => $booking->id,
                'overlap_minutes' => $overlapMinutes,
                'running_total_minutes' => $totalMinutes,
            ]);
        }

        // Ensure total minutes is never negative
        $totalMinutes = max(0, $totalMinutes);
        
        Log::debug("Total booked time calculation completed", [
            'final_total_minutes' => $totalMinutes,
            'final_total_hours' => $totalMinutes / 60,
        ]);

        return $totalMinutes;
    }

    /**
     * Check if a booking has valid start and end times.
     */
    private function isValidBooking(Booking $booking): bool
    {
        if (!$booking->start_time || !$booking->end_time) {
            Log::warning("Booking {$booking->id} has invalid start/end time", [
                'booking_id' => $booking->id,
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time
            ]);
            return false;
        }
        
        return true;
    }

    /**
     * Normalize datetime to Carbon instance.
     */
    private function normalizeDateTime($datetime): Carbon
    {
        return $datetime instanceof Carbon ? $datetime : Carbon::parse($datetime);
    }

    /**
     * Log invalid booking details.
     */
    private function logInvalidBooking(Booking $booking, Carbon $start, Carbon $end): void
    {
        Log::warning("Booking {$booking->id} has invalid time range", [
            'booking_id' => $booking->id,
            'start_time' => $start->toDateTimeString(),
            'end_time' => $end->toDateTimeString()
        ]);
    }

    /**
     * Calculate the overlap between a booking and the report period.
     */
    private function calculateBookingOverlap(Carbon $bookingStart, Carbon $bookingEnd, array $dateRange): int
    {
        $overlapStart = $bookingStart->max($dateRange['start']);
        $overlapEnd = $bookingEnd->min($dateRange['end']);

        if ($overlapStart->lessThan($overlapEnd)) {
            // Use abs() to ensure we always get a positive value
            $overlapMinutes = abs($overlapEnd->diffInMinutes($overlapStart));
            
            Log::debug("Booking overlap calculated", [
                'booking_start' => $bookingStart->toDateTimeString(),
                'booking_end' => $bookingEnd->toDateTimeString(),
                'report_start' => $dateRange['start']->toDateTimeString(),
                'report_end' => $dateRange['end']->toDateTimeString(),
                'overlap_start' => $overlapStart->toDateTimeString(),
                'overlap_end' => $overlapEnd->toDateTimeString(),
                'overlap_minutes' => $overlapMinutes
            ]);
            
            return $overlapMinutes;
        }

        return 0;
    }

    /**
     * Calculate utilization percentage with bounds checking.
     */
    private function calculateUtilizationPercentage(float $bookedHours, float $availableHours): float
    {
        if ($availableHours <= 0) {
            return 0.0;
        }

        $percentage = ($bookedHours / $availableHours) * 100;
        return max(0, min(100, round($percentage, 2)));
    }

    /**
     * Create a successful response with report data.
     */
    private function createSuccessResponse(array $reportData, array $dateRange): array
    {
        return [
            'success' => true,
            'report' => $reportData,
            'period' => [
                'start_date' => $dateRange['start']->toDateString(),
                'end_date' => $dateRange['end']->toDateString()
            ],
            'summary' => [
                'total_resources' => count($reportData),
                'average_utilization' => $this->calculateAverageUtilization($reportData),
                'highest_utilization' => $this->findHighestUtilization($reportData),
                'lowest_utilization' => $this->findLowestUtilization($reportData),
            ]
        ];
    }

    /**
     * Create an error response with appropriate message.
     */
    private function createErrorResponse(string $message, array $dateRange): array
    {
        return [
            'success' => false,
            'message' => $message,
            'report' => [],
            'period' => [
                'start_date' => $dateRange['start']->toDateString(),
                'end_date' => $dateRange['end']->toDateString()
            ]
        ];
    }

    /**
     * Calculate average utilization across all resources.
     */
    private function calculateAverageUtilization(array $reportData): float
    {
        if (empty($reportData)) {
            return 0.0;
        }

        $totalUtilization = array_sum(array_column($reportData, 'utilization_percentage'));
        return round($totalUtilization / count($reportData), 2);
    }

    /**
     * Find the resource with highest utilization.
     */
    private function findHighestUtilization(array $reportData): ?array
    {
        if (empty($reportData)) {
            return null;
        }

        $highest = max(array_column($reportData, 'utilization_percentage'));
        $resource = array_filter($reportData, fn($item) => $item['utilization_percentage'] === $highest);
        
        return reset($resource) ?: null;
    }

    /**
     * Find the resource with lowest utilization.
     */
    private function findLowestUtilization(array $reportData): ?array
    {
        if (empty($reportData)) {
            return null;
        }

        $lowest = min(array_column($reportData, 'utilization_percentage'));
        $resource = array_filter($reportData, fn($item) => $item['utilization_percentage'] === $lowest);
        
        return reset($resource) ?: null;
    }

    /**
     * Generate a comprehensive booking summary report with filters and breakdowns.
     *
     * @param array $filters Array containing filter parameters
     * @return array Report data with success status and summary metrics
     */
    public function getBookingSummaryReport(array $filters = []): array
    {
        try {
            $this->logBookingSummaryRequest($filters);
            
            $dateRange = $this->prepareDateRange(
                $filters['start_date'] ?? null,
                $filters['end_date'] ?? null
            );
            
            if (!$this->isValidDateRange($dateRange)) {
                return $this->createErrorResponse('Start date cannot be after end date.', $dateRange);
            }

            $bookings = $this->getFilteredBookings($filters, $dateRange);
            $reportData = $this->generateBookingSummaryData($bookings, $filters, $dateRange);

            return $this->createBookingSummarySuccessResponse($reportData, $dateRange, $filters);

        } catch (\Exception $e) {
            Log::error("Error generating booking summary report: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'filters' => $filters
            ]);
            
            return $this->createErrorResponse(
                'An error occurred while generating the booking summary report. Please try again.',
                $this->prepareDateRange($filters['start_date'] ?? null, $filters['end_date'] ?? null)
            );
        }
    }

    /**
     * Log the booking summary report request for debugging purposes.
     */
    private function logBookingSummaryRequest(array $filters): void
    {
        Log::debug("Booking summary report request received", [
            'filters' => $filters
        ]);
    }

    /**
     * Get filtered bookings based on provided criteria.
     */
    private function getFilteredBookings(array $filters, array $dateRange): \Illuminate\Database\Eloquent\Collection
    {
        $query = Booking::with(['user:id,first_name,last_name,user_type', 'resource:id,name,category'])
            ->whereIn('status', self::BOOKING_STATUSES);

        // Apply date range filter
        $query->where(function (Builder $q) use ($dateRange) {
            $q->whereBetween('start_time', [$dateRange['start'], $dateRange['end']])
              ->orWhereBetween('end_time', [$dateRange['start'], $dateRange['end']])
              ->orWhere(function (Builder $q) use ($dateRange) {
                  $q->where('start_time', '<=', $dateRange['start'])
                    ->where('end_time', '>=', $dateRange['end']);
              });
        });

        // Apply resource type filter
        if (!empty($filters['resource_type'])) {
            $query->whereHas('resource', function (Builder $q) use ($filters) {
                $q->where('category', $filters['resource_type']);
            });
        }

        // Apply specific resource filter
        if (!empty($filters['resource_id'])) {
            $query->where('resource_id', $filters['resource_id']);
        }

        // Apply user filter
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Apply user type filter
        if (!empty($filters['user_type'])) {
            $query->whereHas('user', function (Builder $q) use ($filters) {
                $q->where('user_type', $filters['user_type']);
            });
        }

        return $query->get();
    }

    /**
     * Generate booking summary data with metrics and breakdowns.
     */
    private function generateBookingSummaryData(\Illuminate\Database\Eloquent\Collection $bookings, array $filters, array $dateRange): array
    {
        // Calculate metrics
        $metrics = $this->calculateBookingMetrics($bookings);
        
        // Generate breakdowns
        $breakdowns = $this->generateBookingBreakdowns($bookings, $filters);

        // Add detailed bookings (customize fields as needed)
        $detailedBookings = $bookings->map(function($booking) {
            return [
                'booking_reference' => $booking->booking_reference,
                'user' => $booking->user ? $booking->user->first_name . ' ' . $booking->user->last_name : null,
                'user_type' => $booking->user->user_type ?? null,
                'resource' => $booking->resource ? $booking->resource->name : null,
                'resource_category' => $booking->resource ? $booking->resource->category : null,
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
                'status' => $booking->status,
            ];
        });

        return [
            'metrics' => $metrics,
            'breakdowns' => $breakdowns,
            'bookings' => $detailedBookings,
        ];
    }

    /**
     * Calculate booking metrics.
     */
    private function calculateBookingMetrics(\Illuminate\Database\Eloquent\Collection $bookings): array
    {
        $totalBookings = $bookings->count();
        $totalBookedMinutes = 0;
        $validBookings = 0;

        foreach ($bookings as $booking) {
            if ($this->isValidBooking($booking)) {
                $duration = $this->calculateBookingDuration($booking);
                $totalBookedMinutes += $duration;
                $validBookings++;
            }
        }

        $totalBookedHours = $totalBookedMinutes / 60;
        $averageBookingDuration = $validBookings > 0 ? $totalBookedHours / $validBookings : 0;
        $uniqueUsers = $bookings->pluck('user_id')->unique()->count();

        return [
            'total_bookings' => $totalBookings,
            'total_booked_hours' => round($totalBookedHours, 2),
            'average_booking_duration' => round($averageBookingDuration, 2),
            'unique_users' => $uniqueUsers,
            'valid_bookings_count' => $validBookings
        ];
    }

    /**
     * Calculate booking duration in minutes.
     */
    private function calculateBookingDuration(Booking $booking): int
    {
        $bookingStart = $this->normalizeDateTime($booking->start_time);
        $bookingEnd = $this->normalizeDateTime($booking->end_time);
        
        if ($bookingEnd->lessThanOrEqualTo($bookingStart)) {
            return 0;
        }
        
        return abs($bookingEnd->diffInMinutes($bookingStart));
    }

    /**
     * Generate booking breakdowns by different criteria.
     */
    private function generateBookingBreakdowns(\Illuminate\Database\Eloquent\Collection $bookings, array $filters): array
    {
        $breakdowns = [];

        // Breakdown by Resource
        $breakdowns['by_resource'] = $this->generateResourceBreakdown($bookings);
        
        // Breakdown by User/User Type
        $breakdowns['by_user_type'] = $this->generateUserTypeBreakdown($bookings);
        
        // Breakdown by Time Periods
        $breakdowns['by_day'] = $this->generateTimeBreakdown($bookings, 'day');
        $breakdowns['by_week'] = $this->generateTimeBreakdown($bookings, 'week');
        $breakdowns['by_month'] = $this->generateTimeBreakdown($bookings, 'month');

        return $breakdowns;
    }

    /**
     * Generate breakdown by resource.
     */
    private function generateResourceBreakdown(\Illuminate\Database\Eloquent\Collection $bookings): array
    {
        $resourceBreakdown = $bookings->groupBy('resource_id')->map(function ($resourceBookings, $resourceId) {
            $resource = $resourceBookings->first()->resource;
            $totalBookedMinutes = 0;
            $validBookings = 0;

            foreach ($resourceBookings as $booking) {
                if ($this->isValidBooking($booking)) {
                    $duration = $this->calculateBookingDuration($booking);
                    $totalBookedMinutes += $duration;
                    $validBookings++;
                }
            }

            return [
                'resource_id' => $resourceId,
                'resource_name' => $resource ? $resource->name : 'Unknown Resource',
                'resource_category' => $resource ? $resource->category : 'Unknown',
                'total_bookings' => $resourceBookings->count(),
                'total_booked_hours' => round($totalBookedMinutes / 60, 2),
                'average_duration' => $validBookings > 0 ? round(($totalBookedMinutes / 60) / $validBookings, 2) : 0,
                'unique_users' => $resourceBookings->pluck('user_id')->unique()->count()
            ];
        })->values()->toArray();

        return $resourceBreakdown;
    }

    /**
     * Generate breakdown by user type.
     */
    private function generateUserTypeBreakdown(\Illuminate\Database\Eloquent\Collection $bookings): array
    {
        $userTypeBreakdown = $bookings->groupBy('user.user_type')->map(function ($userTypeBookings, $userType) {
            $totalBookedMinutes = 0;
            $validBookings = 0;

            foreach ($userTypeBookings as $booking) {
                if ($this->isValidBooking($booking)) {
                    $duration = $this->calculateBookingDuration($booking);
                    $totalBookedMinutes += $duration;
                    $validBookings++;
                }
            }

            return [
                'user_type' => $userType ?? 'Unknown',
                'total_bookings' => $userTypeBookings->count(),
                'total_booked_hours' => round($totalBookedMinutes / 60, 2),
                'average_duration' => $validBookings > 0 ? round(($totalBookedMinutes / 60) / $validBookings, 2) : 0,
                'unique_users' => $userTypeBookings->pluck('user_id')->unique()->count()
            ];
        })->values()->toArray();

        return $userTypeBreakdown;
    }

    /**
     * Generate breakdown by time periods (day, week, month).
     */
    private function generateTimeBreakdown(\Illuminate\Database\Eloquent\Collection $bookings, string $period): array
    {
        $groupedBookings = $bookings->groupBy(function ($booking) use ($period) {
            switch ($period) {
                case 'day':
                    return $booking->start_time->format('Y-m-d');
                case 'week':
                    return $booking->start_time->format('o-W'); // ISO-8601 week number
                case 'month':
                    return $booking->start_time->format('Y-m');
                default:
                    return $booking->start_time->format('Y-m-d');
            }
        });

        $timeBreakdown = $groupedBookings->map(function ($periodBookings, $periodKey) use ($period) {
            $totalBookedMinutes = 0;
            $validBookings = 0;

            foreach ($periodBookings as $booking) {
                if ($this->isValidBooking($booking)) {
                    $duration = $this->calculateBookingDuration($booking);
                    $totalBookedMinutes += $duration;
                    $validBookings++;
                }
            }

            return [
                'period' => $periodKey,
                'period_type' => $period,
                'total_bookings' => $periodBookings->count(),
                'total_booked_hours' => round($totalBookedMinutes / 60, 2),
                'average_duration' => $validBookings > 0 ? round(($totalBookedMinutes / 60) / $validBookings, 2) : 0,
                'unique_users' => $periodBookings->pluck('user_id')->unique()->count()
            ];
        })->values()->toArray();

        // Sort by period
        usort($timeBreakdown, function ($a, $b) {
            return $a['period'] <=> $b['period'];
        });

        return $timeBreakdown;
    }

    /**
     * Create a successful response for booking summary report.
     */
    private function createBookingSummarySuccessResponse(array $reportData, array $dateRange, array $filters): array
    {
        return [
            'success' => true,
            'report' => $reportData,
            'period' => [
                'start_date' => $dateRange['start']->toDateString(),
                'end_date' => $dateRange['end']->toDateString()
            ],
            'filters_applied' => $filters
        ];
    }

    /**
     * Generate an upcoming bookings report with comprehensive filtering options.
     *
     * @param array $filters Array of filter parameters
     * @return array Report data with success status and upcoming bookings
     */
    public function getUpcomingBookingsReport(array $filters = []): array
    {
        try {
            $this->logUpcomingBookingsRequest($filters);
            
            $dateRange = $this->prepareUpcomingDateRange($filters);
            $bookings = $this->getFilteredUpcomingBookings($filters, $dateRange);
            
            $reportData = $this->generateUpcomingBookingsData($bookings, $filters, $dateRange);

            return $this->createUpcomingBookingsSuccessResponse($reportData, $dateRange, $filters);

        } catch (\Exception $e) {
            Log::error("Error generating upcoming bookings report: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'filters' => $filters
            ]);
            
            return $this->createErrorResponse(
                'An error occurred while generating the upcoming bookings report. Please try again.',
                $this->prepareUpcomingDateRange($filters)
            );
        }
    }

    /**
     * Log the upcoming bookings report request for debugging purposes.
     */
    private function logUpcomingBookingsRequest(array $filters): void
    {
        Log::debug("Upcoming bookings report request received", [
            'filters' => $filters
        ]);
    }

    /**
     * Prepare date range for upcoming bookings (defaults to future dates).
     */
    private function prepareUpcomingDateRange(array $filters): array
    {
        $start = isset($filters['start_date']) 
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : Carbon::now()->startOfDay();
            
        $end = isset($filters['end_date']) 
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : Carbon::now()->addDays(30)->endOfDay(); // Default to next 30 days

        // Ensure we're only looking at future bookings
        if ($start->isPast()) {
            $start = Carbon::now()->startOfDay();
        }

        Log::debug("Upcoming bookings date range prepared", [
            'start_formatted' => $start->toDateTimeString(),
            'end_formatted' => $end->toDateTimeString()
        ]);

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Get filtered upcoming bookings based on provided filters.
     */
    private function getFilteredUpcomingBookings(array $filters, array $dateRange): \Illuminate\Database\Eloquent\Collection
    {
        $query = Booking::with([
            'user:id,first_name,last_name,email,user_type',
            'resource:id,name,description,location,category,capacity',
            'approvedBy:id,first_name,last_name',
            'rejectedBy:id,first_name,last_name',
            'cancelledBy:id,first_name,last_name'
        ])
        ->where('start_time', '>=', $dateRange['start'])
        ->where('start_time', '<=', $dateRange['end'])
        ->whereIn('status', ['approved', 'pending', 'in_use']);

        // Apply resource filters
        if (isset($filters['resource_id'])) {
            $query->where('resource_id', $filters['resource_id']);
        }

        if (isset($filters['resource_type'])) {
            $query->whereHas('resource', function ($q) use ($filters) {
                $q->where('category', $filters['resource_type']);
            });
        }

        // Apply user filters
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['user_type'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('user_type', $filters['user_type']);
            });
        }

        // Apply status filter
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply limit
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 100;
        $query->limit($limit);

        return $query->orderBy('start_time', 'asc')->get();
    }

    /**
     * Generate comprehensive upcoming bookings data.
     */
    private function generateUpcomingBookingsData(\Illuminate\Database\Eloquent\Collection $bookings, array $filters, array $dateRange): array
    {
        $bookingsData = [];
        $summary = $this->calculateUpcomingBookingsSummary($bookings);

        foreach ($bookings as $booking) {
            $bookingsData[] = [
                'id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'resource' => [
                    'id' => $booking->resource->id,
                    'name' => $booking->resource->name,
                    'description' => $booking->resource->description,
                    'location' => $booking->resource->location,
                    'category' => $booking->resource->category,
                    'capacity' => $booking->resource->capacity,
                ],
                'user' => [
                    'id' => $booking->user->id,
                    'name' => $booking->user->first_name . ' ' . $booking->user->last_name,
                    'email' => $booking->user->email,
                    'user_type' => $booking->user->user_type,
                ],
                'schedule' => [
                    'start_time' => $booking->start_time->toISOString(),
                    'end_time' => $booking->end_time->toISOString(),
                    'date' => $booking->start_time->format('Y-m-d'),
                    'start_time_formatted' => $booking->start_time->format('H:i'),
                    'end_time_formatted' => $booking->end_time->format('H:i'),
                    'duration_hours' => round($booking->start_time->diffInHours($booking->end_time), 2),
                    'duration_minutes' => $booking->start_time->diffInMinutes($booking->end_time),
                ],
                'details' => [
                    'purpose' => $booking->purpose,
                    'booking_type' => $booking->booking_type,
                    'status' => $booking->status,
                    'priority' => $booking->priority,
                ],
                'approval_info' => [
                    'approved_by' => $booking->approvedBy ? [
                        'id' => $booking->approvedBy->id,
                        'name' => $booking->approvedBy->first_name . ' ' . $booking->approvedBy->last_name,
                    ] : null,
                    'approved_at' => $booking->approved_at ? $booking->approved_at->toISOString() : null,
                    'rejected_by' => $booking->rejectedBy ? [
                        'id' => $booking->rejectedBy->id,
                        'name' => $booking->rejectedBy->first_name . ' ' . $booking->rejectedBy->last_name,
                    ] : null,
                    'rejected_at' => $booking->rejected_at ? $booking->rejected_at->toISOString() : null,
                    'rejection_reason' => $booking->rejection_reason,
                ],
                'cancellation_info' => [
                    'cancelled_by' => $booking->cancelledBy ? [
                        'id' => $booking->cancelledBy->id,
                        'name' => $booking->cancelledBy->first_name . ' ' . $booking->cancelledBy->last_name,
                    ] : null,
                    'cancelled_at' => $booking->cancelled_at ? $booking->cancelled_at->toISOString() : null,
                    'cancellation_reason' => $booking->cancellation_reason,
                ],
                'document_info' => [
                    'has_supporting_document' => !empty($booking->supporting_document_path),
                    'document_path' => $booking->supporting_document_path,
                ],
                'created_at' => $booking->created_at->toISOString(),
                'updated_at' => $booking->updated_at->toISOString(),
            ];
        }

        return [
            'bookings' => $bookingsData,
            'summary' => $summary,
            'filters_applied' => $filters,
        ];
    }

    /**
     * Calculate summary statistics for upcoming bookings.
     */
    private function calculateUpcomingBookingsSummary(\Illuminate\Database\Eloquent\Collection $bookings): array
    {
        $totalBookings = $bookings->count();
        $totalHours = $bookings->sum(function ($booking) {
            return $booking->start_time->diffInHours($booking->end_time);
        });

        $statusBreakdown = $bookings->groupBy('status')->map(function ($group) {
            return $group->count();
        });

        $resourceTypeBreakdown = $bookings->groupBy('resource.category')->map(function ($group) {
            return $group->count();
        });

        $userTypeBreakdown = $bookings->groupBy('user.user_type')->map(function ($group) {
            return $group->count();
        });

        $bookingTypeBreakdown = $bookings->groupBy('booking_type')->map(function ($group) {
            return $group->count();
        });

        return [
            'total_bookings' => $totalBookings,
            'total_hours' => round($totalHours, 2),
            'average_duration_hours' => $totalBookings > 0 ? round($totalHours / $totalBookings, 2) : 0,
            'status_breakdown' => $statusBreakdown,
            'resource_type_breakdown' => $resourceTypeBreakdown,
            'user_type_breakdown' => $userTypeBreakdown,
            'booking_type_breakdown' => $bookingTypeBreakdown,
        ];
    }

    /**
     * Create success response for upcoming bookings report.
     */
    private function createUpcomingBookingsSuccessResponse(array $reportData, array $dateRange, array $filters): array
    {
        return [
            'success' => true,
            'report' => $reportData,
            'period' => [
                'start_date' => $dateRange['start']->format('Y-m-d'),
                'end_date' => $dateRange['end']->format('Y-m-d'),
                'start_datetime' => $dateRange['start']->toISOString(),
                'end_datetime' => $dateRange['end']->toISOString(),
            ],
            'filters_applied' => $filters,
            'total_bookings' => $reportData['summary']['total_bookings'],
        ];
    }

    /**
     * Generate a comprehensive canceled bookings report with filters and breakdowns.
     *
     * @param array $filters Array containing filter parameters
     * @return array Report data with success status and summary metrics
     */
    public function getCanceledBookingsReport(array $filters = []): array
    {
        try {
            $this->logCanceledBookingsRequest($filters);
            
            $dateRange = $this->prepareDateRange(
                $filters['start_date'] ?? null,
                $filters['end_date'] ?? null
            );
            
            if (!$this->isValidDateRange($dateRange)) {
                return $this->createErrorResponse('Start date cannot be after end date.', $dateRange);
            }

            $canceledBookings = $this->getFilteredCanceledBookings($filters, $dateRange);
            $totalBookings = $this->getTotalBookingsInPeriod($filters, $dateRange);
            $reportData = $this->generateCanceledBookingsData($canceledBookings, $totalBookings, $filters, $dateRange);

            return $this->createCanceledBookingsSuccessResponse($reportData, $dateRange, $filters);

        } catch (\Exception $e) {
            Log::error("Error generating canceled bookings report: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'filters' => $filters
            ]);
            
            return $this->createErrorResponse(
                'An error occurred while generating the canceled bookings report. Please try again.',
                $this->prepareDateRange($filters['start_date'] ?? null, $filters['end_date'] ?? null)
            );
        }
    }

    /**
     * Log the canceled bookings report request for debugging purposes.
     */
    private function logCanceledBookingsRequest(array $filters): void
    {
        Log::debug("Canceled bookings report request received", [
            'filters' => $filters
        ]);
    }

    /**
     * Get filtered canceled bookings based on provided criteria.
     */
    private function getFilteredCanceledBookings(array $filters, array $dateRange): \Illuminate\Database\Eloquent\Collection
    {
        $query = Booking::with([
            'user:id,first_name,last_name,email,user_type',
            'resource:id,name,description,location,category,capacity',
            'cancelledBy:id,first_name,last_name'
        ])
        ->where('status', Booking::STATUS_CANCELLED);

        // Apply date range filter for cancellation date
        $query->where(function (Builder $q) use ($dateRange) {
            $q->whereBetween('cancelled_at', [$dateRange['start'], $dateRange['end']])
              ->orWhere(function (Builder $q) use ($dateRange) {
                  $q->where('cancelled_at', '>=', $dateRange['start'])
                    ->where('cancelled_at', '<=', $dateRange['end']);
              });
        });

        // Apply resource type filter
        if (!empty($filters['resource_type'])) {
            $query->whereHas('resource', function (Builder $q) use ($filters) {
                $q->where('category', $filters['resource_type']);
            });
        }

        // Apply specific resource filter
        if (!empty($filters['resource_id'])) {
            $query->where('resource_id', $filters['resource_id']);
        }

        // Apply user filter
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Apply user type filter
        if (!empty($filters['user_type'])) {
            $query->whereHas('user', function (Builder $q) use ($filters) {
                $q->where('user_type', $filters['user_type']);
            });
        }

        return $query->orderBy('cancelled_at', 'desc')->get();
    }

    /**
     * Get total bookings in the period for percentage calculation.
     */
    private function getTotalBookingsInPeriod(array $filters, array $dateRange): int
    {
        $query = Booking::where(function (Builder $q) use ($dateRange) {
            $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
              ->orWhere(function (Builder $q) use ($dateRange) {
                  $q->where('created_at', '>=', $dateRange['start'])
                    ->where('created_at', '<=', $dateRange['end']);
              });
        });

        // Apply resource type filter
        if (!empty($filters['resource_type'])) {
            $query->whereHas('resource', function (Builder $q) use ($filters) {
                $q->where('category', $filters['resource_type']);
            });
        }

        // Apply specific resource filter
        if (!empty($filters['resource_id'])) {
            $query->where('resource_id', $filters['resource_id']);
        }

        // Apply user filter
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Apply user type filter
        if (!empty($filters['user_type'])) {
            $query->whereHas('user', function (Builder $q) use ($filters) {
                $q->where('user_type', $filters['user_type']);
            });
        }

        return $query->count();
    }

    /**
     * Generate canceled bookings data with metrics and breakdowns.
     */
    private function generateCanceledBookingsData(\Illuminate\Database\Eloquent\Collection $canceledBookings, int $totalBookings, array $filters, array $dateRange): array
    {
        // Calculate metrics
        $metrics = $this->calculateCanceledBookingsMetrics($canceledBookings, $totalBookings);
        
        // Generate breakdowns
        $breakdowns = $this->generateCanceledBookingsBreakdowns($canceledBookings, $filters);

        // Add detailed canceled bookings
        $detailedCanceledBookings = $canceledBookings->map(function($booking) {
            return [
                'id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'user' => [
                    'id' => $booking->user->id,
                    'name' => $booking->user->first_name . ' ' . $booking->user->last_name,
                    'email' => $booking->user->email,
                    'user_type' => $booking->user->user_type,
                ],
                'resource' => [
                    'id' => $booking->resource->id,
                    'name' => $booking->resource->name,
                    'description' => $booking->resource->description,
                    'location' => $booking->resource->location,
                    'category' => $booking->resource->category,
                    'capacity' => $booking->resource->capacity,
                ],
                'original_schedule' => [
                    'start_time' => $booking->start_time->toISOString(),
                    'end_time' => $booking->end_time->toISOString(),
                    'date' => $booking->start_time->format('Y-m-d'),
                    'start_time_formatted' => $booking->start_time->format('H:i'),
                    'end_time_formatted' => $booking->end_time->format('H:i'),
                    'duration_hours' => round($booking->start_time->diffInHours($booking->end_time), 2),
                ],
                'cancellation_details' => [
                    'cancelled_by' => $booking->cancelledBy ? [
                        'id' => $booking->cancelledBy->id,
                        'name' => $booking->cancelledBy->first_name . ' ' . $booking->cancelledBy->last_name,
                    ] : null,
                    'cancelled_at' => $booking->cancelled_at ? $booking->cancelled_at->toISOString() : null,
                    'cancellation_reason' => $booking->cancellation_reason,
                    'refund_amount' => $booking->refund_amount,
                ],
                'booking_details' => [
                    'purpose' => $booking->purpose,
                    'booking_type' => $booking->booking_type,
                    'priority' => $booking->priority,
                ],
                'created_at' => $booking->created_at->toISOString(),
                'updated_at' => $booking->updated_at->toISOString(),
            ];
        });

        return [
            'metrics' => $metrics,
            'breakdowns' => $breakdowns,
            'canceled_bookings' => $detailedCanceledBookings,
        ];
    }

    /**
     * Calculate canceled bookings metrics.
     */
    private function calculateCanceledBookingsMetrics(\Illuminate\Database\Eloquent\Collection $canceledBookings, int $totalBookings): array
    {
        $totalCancelations = $canceledBookings->count();
        $cancellationPercentage = $totalBookings > 0 ? round(($totalCancelations / $totalBookings) * 100, 2) : 0;
        
        // Calculate total refund amount
        $totalRefundAmount = $canceledBookings->sum('refund_amount');
        
        // Calculate average time between booking creation and cancellation
        $totalCancellationTime = 0;
        $validCancelations = 0;
        
        foreach ($canceledBookings as $booking) {
            if ($booking->cancelled_at && $booking->created_at) {
                $cancellationTime = $booking->cancelled_at->diffInHours($booking->created_at);
                $totalCancellationTime += $cancellationTime;
                $validCancelations++;
            }
        }
        
        $averageCancellationTime = $validCancelations > 0 ? round($totalCancellationTime / $validCancelations, 2) : 0;
        
        // Count unique users who canceled
        $uniqueUsersCanceled = $canceledBookings->pluck('user_id')->unique()->count();
        
        // Count unique resources that were canceled
        $uniqueResourcesCanceled = $canceledBookings->pluck('resource_id')->unique()->count();

        return [
            'total_cancelations' => $totalCancelations,
            'cancellation_percentage' => $cancellationPercentage,
            'total_refund_amount' => round($totalRefundAmount, 2),
            'average_cancellation_time_hours' => $averageCancellationTime,
            'unique_users_canceled' => $uniqueUsersCanceled,
            'unique_resources_canceled' => $uniqueResourcesCanceled,
            'total_bookings_in_period' => $totalBookings,
        ];
    }

    /**
     * Generate canceled bookings breakdowns by different criteria.
     */
    private function generateCanceledBookingsBreakdowns(\Illuminate\Database\Eloquent\Collection $canceledBookings, array $filters): array
    {
        $breakdowns = [];

        // Breakdown by Resource
        $breakdowns['by_resource'] = $this->generateCanceledResourceBreakdown($canceledBookings);
        
        // Breakdown by User Type
        $breakdowns['by_user_type'] = $this->generateCanceledUserTypeBreakdown($canceledBookings);
        
        // Breakdown by Cancellation Reason
        $breakdowns['by_cancellation_reason'] = $this->generateCancellationReasonBreakdown($canceledBookings);
        
        // Breakdown by Time Periods
        $breakdowns['by_day'] = $this->generateCanceledTimeBreakdown($canceledBookings, 'day');
        $breakdowns['by_week'] = $this->generateCanceledTimeBreakdown($canceledBookings, 'week');
        $breakdowns['by_month'] = $this->generateCanceledTimeBreakdown($canceledBookings, 'month');

        return $breakdowns;
    }

    /**
     * Generate breakdown by resource for canceled bookings.
     */
    private function generateCanceledResourceBreakdown(\Illuminate\Database\Eloquent\Collection $canceledBookings): array
    {
        $resourceBreakdown = $canceledBookings->groupBy('resource_id')->map(function ($resourceBookings, $resourceId) {
            $resource = $resourceBookings->first()->resource;
            $totalRefundAmount = $resourceBookings->sum('refund_amount');

            return [
                'resource_id' => $resourceId,
                'resource_name' => $resource ? $resource->name : 'Unknown Resource',
                'resource_category' => $resource ? $resource->category : 'Unknown',
                'total_cancelations' => $resourceBookings->count(),
                'total_refund_amount' => round($totalRefundAmount, 2),
                'unique_users_canceled' => $resourceBookings->pluck('user_id')->unique()->count(),
                'average_refund_amount' => $resourceBookings->count() > 0 ? round($totalRefundAmount / $resourceBookings->count(), 2) : 0,
            ];
        })->values()->toArray();

        return $resourceBreakdown;
    }

    /**
     * Generate breakdown by user type for canceled bookings.
     */
    private function generateCanceledUserTypeBreakdown(\Illuminate\Database\Eloquent\Collection $canceledBookings): array
    {
        $userTypeBreakdown = $canceledBookings->groupBy('user.user_type')->map(function ($userTypeBookings, $userType) {
            $totalRefundAmount = $userTypeBookings->sum('refund_amount');

            return [
                'user_type' => $userType ?? 'Unknown',
                'total_cancelations' => $userTypeBookings->count(),
                'total_refund_amount' => round($totalRefundAmount, 2),
                'unique_users' => $userTypeBookings->pluck('user_id')->unique()->count(),
                'average_refund_amount' => $userTypeBookings->count() > 0 ? round($totalRefundAmount / $userTypeBookings->count(), 2) : 0,
            ];
        })->values()->toArray();

        return $userTypeBreakdown;
    }

    /**
     * Generate breakdown by cancellation reason.
     */
    private function generateCancellationReasonBreakdown(\Illuminate\Database\Eloquent\Collection $canceledBookings): array
    {
        $reasonBreakdown = $canceledBookings->groupBy('cancellation_reason')->map(function ($reasonBookings, $reason) {
            $totalRefundAmount = $reasonBookings->sum('refund_amount');

            return [
                'cancellation_reason' => $reason ?? 'No reason provided',
                'total_cancelations' => $reasonBookings->count(),
                'total_refund_amount' => round($totalRefundAmount, 2),
                'unique_users' => $reasonBookings->pluck('user_id')->unique()->count(),
                'average_refund_amount' => $reasonBookings->count() > 0 ? round($totalRefundAmount / $reasonBookings->count(), 2) : 0,
            ];
        })->values()->toArray();

        return $reasonBreakdown;
    }

    /**
     * Generate breakdown by time periods for canceled bookings.
     */
    private function generateCanceledTimeBreakdown(\Illuminate\Database\Eloquent\Collection $canceledBookings, string $period): array
    {
        $groupedBookings = $canceledBookings->groupBy(function ($booking) use ($period) {
            switch ($period) {
                case 'day':
                    return $booking->cancelled_at ? $booking->cancelled_at->format('Y-m-d') : 'Unknown';
                case 'week':
                    return $booking->cancelled_at ? $booking->cancelled_at->format('o-W') : 'Unknown';
                case 'month':
                    return $booking->cancelled_at ? $booking->cancelled_at->format('Y-m') : 'Unknown';
                default:
                    return $booking->cancelled_at ? $booking->cancelled_at->format('Y-m-d') : 'Unknown';
            }
        });

        $timeBreakdown = $groupedBookings->map(function ($periodBookings, $periodKey) use ($period) {
            $totalRefundAmount = $periodBookings->sum('refund_amount');

            return [
                'period' => $periodKey,
                'period_type' => $period,
                'total_cancelations' => $periodBookings->count(),
                'total_refund_amount' => round($totalRefundAmount, 2),
                'unique_users' => $periodBookings->pluck('user_id')->unique()->count(),
                'average_refund_amount' => $periodBookings->count() > 0 ? round($totalRefundAmount / $periodBookings->count(), 2) : 0,
            ];
        })->values()->toArray();

        // Sort by period
        usort($timeBreakdown, function ($a, $b) {
            return $a['period'] <=> $b['period'];
        });

        return $timeBreakdown;
    }

    /**
     * Create a successful response for canceled bookings report.
     */
    private function createCanceledBookingsSuccessResponse(array $reportData, array $dateRange, array $filters): array
    {
        return [
            'success' => true,
            'report' => $reportData,
            'period' => [
                'start_date' => $dateRange['start']->format('Y-m-d'),
                'end_date' => $dateRange['end']->format('Y-m-d'),
                'start_datetime' => $dateRange['start']->toISOString(),
                'end_datetime' => $dateRange['end']->toISOString(),
            ],
            'filters_applied' => $filters,
            'total_cancelations' => $reportData['metrics']['total_cancelations'],
        ];
    }
} 