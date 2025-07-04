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
        $durationMinutes = abs($dateRange['end']->diffInMinutes($dateRange['start'], false));
        $durationHours = $durationMinutes / 60;

        // Handle edge case of zero duration
        if ($durationMinutes === 0) {
            $durationMinutes = 1;
            $durationHours = 1/60;
        }

        Log::debug("Report period calculated", [
            'duration_minutes' => $durationMinutes,
            'duration_hours' => $durationHours
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

        return [
            'metrics' => $metrics,
            'breakdowns' => $breakdowns
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
} 