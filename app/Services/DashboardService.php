<?php

namespace App\Services;

use App\Exceptions\DashboardException;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardService
{
    /**
     * DashboardService constructor.
     */
    public function __construct()
    {
        // You can inject any dependencies here if needed

        
    }
    /**
     * Fetch key performance indicators (KPIs) for the dashboard.
     *
     * @return array
     * @throws DashboardException
     */
    public function getKpis(): array
    {
        try {
            $totalResources = Resource::count();
            $totalBookings = Booking::count();
            $totalUsers = User::count();

            // Calculate truly available resources: where resource status is 'available'
            $availableResources = Resource::where('status', 'available') 
                ->whereDoesntHave('bookings', function ($query) {
                    $query->where('end_time', '>', Carbon::now())
                          ->where('start_time', '<', Carbon::now()) 
                          ->whereIn('status', [BookingService::STATUS_APPROVED, BookingService::STATUS_PENDING, BookingService::STATUS_IN_USE]);
                })
                ->count();

            // Optionally, count resources that are active but not currently booked (future available)
            $activeButNotCurrentlyBooked = Resource::where('is_active', true)
                ->whereDoesntHave('bookings', function ($query) {
                    $query->where('end_time', '>', Carbon::now())
                          ->where('start_time', '<', Carbon::now())
                          ->whereIn('status', [BookingService::STATUS_APPROVED, BookingService::STATUS_PENDING, BookingService::STATUS_IN_USE]);
                })
                ->count();


            return [
                'total_resources' => $totalResources,
                'total_bookings' => $totalBookings,
                'total_users' => $totalUsers,
                'available_resources' => $availableResources, 
                'active_but_not_currently_booked' => $activeButNotCurrentlyBooked,
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching dashboard KPIs: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new DashboardException('Failed to retrieve key metrics.');
        }
    }

    /**
     * Get booking counts by status for a pie chart.
     *
     * @return array
     * @throws DashboardException
     */
    public function getBookingsByStatus(): array
    {
        try {
            return Booking::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Error fetching bookings by status: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new DashboardException('Failed to retrieve bookings by status.');
        }
    }

    /**
     * Get resource availability overview for a chart.
     * Includes static resource statuses and dynamically calculates currently booked resources.
     *
     * @return array
     * @throws DashboardException
     */
    public function getResourceAvailabilityOverview(): array
    {
        try {
            $resourceAvailability = Resource::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->map(function ($item) {
                    $name = ucfirst($item->status);
                    if ($item->status === 'available') $name = 'Available (Static)'; 
                    if ($item->status === 'maintenance') $name = 'Under Maintenance';
                    return ['name' => $name, 'count' => $item->count];
                })
                ->toArray();

            $currentlyBookedResourcesCount = DB::table('bookings')
                ->where('end_time', '>', Carbon::now())
                ->where('start_time', '<', Carbon::now())
                ->where('status', BookingService::STATUS_APPROVED) 
                ->distinct('resource_id')
                ->count('resource_id');

            $foundBookedStatus = false;
            foreach ($resourceAvailability as &$statusItem) {
                if ($statusItem['name'] === 'Currently Booked') {
                    $statusItem['count'] += $currentlyBookedResourcesCount;
                    $foundBookedStatus = true;
                    break;
                }
            }
            if (!$foundBookedStatus) {
                $resourceAvailability[] = ['name' => 'Currently Booked', 'count' => $currentlyBookedResourcesCount];
            }

            return $resourceAvailability;
        } catch (\Exception $e) {
            Log::error('Error fetching resource availability overview: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new DashboardException('Failed to retrieve resource availability overview.');
        }
    }

    /**
     * Get the top N most booked resources.
     *
     * @param int $limit
     * @return array
     * @throws DashboardException
     */
    public function getTopBookedResources(int $limit = 19): array
    {
        try {
            return DB::table('bookings')
                ->join('resources', 'bookings.resource_id', '=', 'resources.id')
                ->select('resources.id as resource_id', 'resources.name as resource_name', DB::raw('count(bookings.id) as total_bookings'))
                ->groupBy('resources.id', 'resources.name')
                ->orderByDesc('total_bookings')
                ->limit($limit)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Error fetching top booked resources: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new DashboardException('Failed to retrieve top booked resources.');
        }
    }

    /**
     * Get booking trends for a given period (day, week, month, year).
     *
     * @param string $period
     * @param string|null $date
     * @return array
     * @throws DashboardException
     */
    public function getBookingTrends(string $period = 'month', $date = null): array
    {
        try {
            $date = $date ? Carbon::parse($date) : Carbon::now();
            switch ($period) {
                case 'day':
                    $start = $date->copy()->startOfDay();
                    $end = $date->copy()->endOfDay();
                    $bookings = Booking::whereBetween('start_time', [$start, $end])->get();
                    $hours = [];
                    for ($h = 0; $h < 24; $h++) {
                        $hours[sprintf('%02d:00', $h)] = 0;
                    }
                    foreach ($bookings as $booking) {
                        $hour = $booking->start_time->format('H:00');
                        $hours[$hour]++;
                    }
                    $result = [];
                    foreach ($hours as $hour => $count) {
                        $result[] = ['hour' => $hour, 'total_bookings' => $count];
                    }
                    return $result;
                case 'week':
                    $start = $date->copy()->startOfWeek();
                    $end = $date->copy()->endOfWeek();
                    $bookings = Booking::whereBetween('start_time', [$start, $end])->get();
                    $days = [];
                    for ($d = 0; $d < 7; $d++) {
                        $day = $start->copy()->addDays($d)->format('Y-m-d');
                        $days[$day] = 0;
                    }
                    foreach ($bookings as $booking) {
                        $day = $booking->start_time->format('Y-m-d');
                        $days[$day]++;
                    }
                    $result = [];
                    foreach ($days as $day => $count) {
                        $result[] = ['day' => $day, 'total_bookings' => $count];
                    }
                    return $result;
                case 'month':
                    $start = $date->copy()->startOfMonth();
                    $end = $date->copy()->endOfMonth();
                    $bookings = Booking::whereBetween('start_time', [$start, $end])->get();
                    $days = [];
                    $daysInMonth = $date->daysInMonth;
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        $day = $date->copy()->day($d)->format('Y-m-d');
                        $days[$day] = 0;
                    }
                    foreach ($bookings as $booking) {
                        $day = $booking->start_time->format('Y-m-d');
                        $days[$day]++;
                    }
                    $result = [];
                    foreach ($days as $day => $count) {
                        $result[] = ['day' => $day, 'total_bookings' => $count];
                    }
                    return $result;
                case 'year':
                default:
                    $year = $date->year;
                    $bookings = Booking::whereYear('start_time', $year)->get();
                    $months = [];
                    for ($m = 1; $m <= 12; $m++) {
                        $month = sprintf('%04d-%02d', $year, $m);
                        $months[$month] = 0;
                    }
                    foreach ($bookings as $booking) {
                        $month = $booking->start_time->format('Y-m');
                        $months[$month]++;
                    }
                    $result = [];
                    foreach ($months as $month => $count) {
                        $result[] = ['month' => $month, 'total_bookings' => $count];
                    }
                    return $result;
            }
        } catch (\Exception $e) {
            Log::error('Error fetching booking trends: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new DashboardException('Failed to retrieve booking trends.');
        }
    }

    /**
     * Get resource utilization (total booked hours) for a given period (day, week, month, year).
     *
     * @param string $period
     * @param string|null $date
     * @return array
     * @throws DashboardException
     */
    public function getResourceUtilization(string $period = 'month', $date = null): array
    {
        try {
            $date = $date ? Carbon::parse($date) : Carbon::now();
            switch ($period) {
                case 'day':
                    $start = $date->copy()->startOfDay();
                    $end = $date->copy()->endOfDay();
                    $bookings = Booking::whereBetween('start_time', [$start, $end])->get();
                    $hours = [];
                    for ($h = 0; $h < 24; $h++) {
                        $hours[sprintf('%02d:00', $h)] = 0;
                    }
                    foreach ($bookings as $booking) {
                        $hour = $booking->start_time->format('H:00');
                        $duration = $booking->start_time->diffInHours($booking->end_time, false);
                        $hours[$hour] += $duration;
                    }
                    $result = [];
                    foreach ($hours as $hour => $total) {
                        $result[] = ['hour' => $hour, 'total_booked_hours' => round($total, 2)];
                    }
                    return $result;
                case 'week':
                    $start = $date->copy()->startOfWeek();
                    $end = $date->copy()->endOfWeek();
                    $bookings = Booking::whereBetween('start_time', [$start, $end])->get();
                    $days = [];
                    for ($d = 0; $d < 7; $d++) {
                        $day = $start->copy()->addDays($d)->format('Y-m-d');
                        $days[$day] = 0;
                    }
                    foreach ($bookings as $booking) {
                        $day = $booking->start_time->format('Y-m-d');
                        $duration = $booking->start_time->diffInHours($booking->end_time, false);
                        $days[$day] += $duration;
                    }
                    $result = [];
                    foreach ($days as $day => $total) {
                        $result[] = ['day' => $day, 'total_booked_hours' => round($total, 2)];
                    }
                    return $result;
                case 'month':
                    $start = $date->copy()->startOfMonth();
                    $end = $date->copy()->endOfMonth();
                    $bookings = Booking::whereBetween('start_time', [$start, $end])->get();
                    $days = [];
                    $daysInMonth = $date->daysInMonth;
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        $day = $date->copy()->day($d)->format('Y-m-d');
                        $days[$day] = 0;
                    }
                    foreach ($bookings as $booking) {
                        $day = $booking->start_time->format('Y-m-d');
                        $duration = $booking->start_time->diffInHours($booking->end_time, false);
                        $days[$day] += $duration;
                    }
                    $result = [];
                    foreach ($days as $day => $total) {
                        $result[] = ['day' => $day, 'total_booked_hours' => round($total, 2)];
                    }
                    return $result;
                case 'year':
                default:
                    $year = $date->year;
                    $bookings = Booking::whereYear('start_time', $year)->get();
                    $months = [];
                    for ($m = 1; $m <= 12; $m++) {
                        $month = sprintf('%04d-%02d', $year, $m);
                        $months[$month] = 0;
                    }
                    foreach ($bookings as $booking) {
                        $month = $booking->start_time->format('Y-m');
                        $duration = $booking->start_time->diffInHours($booking->end_time, false);
                        $months[$month] += $duration;
                    }
                    $result = [];
                    foreach ($months as $month => $total) {
                        $result[] = ['month' => $month, 'total_booked_hours' => round($total, 2)];
                    }
                    return $result;
            }
        } catch (\Exception $e) {
            Log::error('Error fetching resource utilization: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new DashboardException('Failed to retrieve resource utilization.');
        }
    }

    /**
     * Get the total number of bookings for each resource category (type) with period filtering.
     *
     * @param string $period
     * @param string|null $date
     * @return array
     * @throws DashboardException
     */
    public function getBookingsByResourceType(string $period = 'month', $date = null): array
    {
        try {
            $date = $date ? Carbon::parse($date) : Carbon::now();
            $query = \DB::table('bookings')
                ->join('resources', 'bookings.resource_id', '=', 'resources.id');
            // Filter by period
            switch ($period) {
                case 'day':
                    $query->whereDate('bookings.start_time', $date->toDateString());
                    break;
                case 'week':
                    $query->whereBetween('bookings.start_time', [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereYear('bookings.start_time', $date->year)
                          ->whereMonth('bookings.start_time', $date->month);
                    break;
                case 'year':
                default:
                    $query->whereYear('bookings.start_time', $date->year);
                    break;
            }
            $results = $query->select('resources.category as category', \DB::raw('count(bookings.id) as total_bookings'))
                ->groupBy('resources.category')
                ->orderByDesc('total_bookings')
                ->get();
            return $results->map(function ($row) {
                return [
                    'category' => $row->category,
                    'total_bookings' => (int) $row->total_bookings,
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('Error fetching bookings by resource type: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new DashboardException('Failed to retrieve bookings by resource type.');
        }
    }

    /**
     * Get the distribution of booking durations (in hours) for all bookings, with period filtering.
     *
     * @param string $period
     * @param string|null $date
     * @return array
     * @throws DashboardException
     */
    public function getBookingDurationDistribution(string $period = 'month', $date = null): array
    {
        try {
            $date = $date ? Carbon::parse($date) : Carbon::now();
            $query = \App\Models\Booking::select('start_time', 'end_time');
            switch ($period) {
                case 'day':
                    $query->whereDate('start_time', $date->toDateString());
                    break;
                case 'week':
                    $query->whereBetween('start_time', [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereYear('start_time', $date->year)
                          ->whereMonth('start_time', $date->month);
                    break;
                case 'year':
                default:
                    $query->whereYear('start_time', $date->year);
                    break;
            }
            $bookings = $query->get();
            $durations = $bookings->map(function ($booking) {
                if ($booking->start_time && $booking->end_time) {
                    return round($booking->start_time->floatDiffInHours($booking->end_time), 2);
                }
                return null;
            })->filter(function ($duration) {
                return $duration !== null && $duration > 0;
            })->values()->toArray();
            return $durations;
        } catch (\Exception $e) {
            \Log::error('Error fetching booking duration distribution: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new DashboardException('Failed to retrieve booking duration distribution.');
        }
    }

    /**
     * Get all dashboard data with period filtering.
     *
     * @param string $period
     * @param string|null $date
     * @return array
     * @throws DashboardException
     */
    public function getAllDashboardData(string $period = 'month', $date = null): array
    {
        return [
            'kpis' => $this->getKpis(),
            'bookings_by_status' => $this->getBookingsByStatus(),
            'resource_availability' => $this->getResourceAvailabilityOverview(),
            'top_booked_resources' => $this->getTopBookedResources(),
            'monthly_bookings' => $this->getBookingTrends($period, $date),
            'resource_utilization_monthly' => $this->getResourceUtilization($period, $date),
            'bookings_by_resource_type' => $this->getBookingsByResourceType($period, $date),
            'booking_duration_distribution' => $this->getBookingDurationDistribution($period, $date),
        ];
    }

    /**
     * Get the current cancellation rate as a percentage, with period filtering.
     *
     * @param string $period
     * @param string|null $date
     * @return float
     * @throws DashboardException
     */
    public function getCancellationRate(string $period = 'month', $date = null): float
    {
        try {
            $date = $date ? Carbon::parse($date) : Carbon::now();
            $query = \App\Models\Booking::query();
            switch ($period) {
                case 'day':
                    $query->whereDate('start_time', $date->toDateString());
                    break;
                case 'week':
                    $query->whereBetween('start_time', [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereYear('start_time', $date->year)
                          ->whereMonth('start_time', $date->month);
                    break;
                case 'year':
                default:
                    $query->whereYear('start_time', $date->year);
                    break;
            }
            $total = $query->count();
            if ($total === 0) return 0.0;
            $cancelled = (clone $query)->where('status', \App\Models\Booking::STATUS_CANCELLED)->count();
            return round(($cancelled / $total) * 100, 2);
        } catch (\Exception $e) {
            \Log::error('Error fetching cancellation rate: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new DashboardException('Failed to retrieve cancellation rate.');
        }
    }

    /**
     * Get cancellation rate trends for the given period (day, week, month, year).
     *
     * @param string $period
     * @param string|null $date
     * @return array
     * @throws DashboardException
     */
    public function getCancellationTrends(string $period = 'month', $date = null): array
    {
        try {
            $date = $date ? Carbon::parse($date) : Carbon::now();
            $trends = [];
            switch ($period) {
                case 'day':
                    // For a single day, just return the rate for each hour
                    for ($h = 0; $h < 24; $h++) {
                        $hourStart = $date->copy()->setTime($h, 0, 0);
                        $hourEnd = $hourStart->copy()->endOfHour();
                        $total = \App\Models\Booking::whereBetween('start_time', [$hourStart, $hourEnd])->count();
                        $cancelled = \App\Models\Booking::whereBetween('start_time', [$hourStart, $hourEnd])->where('status', \App\Models\Booking::STATUS_CANCELLED)->count();
                        $rate = $total > 0 ? round(($cancelled / $total) * 100, 2) : 0.0;
                        $trends[] = ['hour' => $hourStart->format('H:00'), 'cancellation_rate' => $rate];
                    }
                    break;
                case 'week':
                    $start = $date->copy()->startOfWeek();
                    for ($d = 0; $d < 7; $d++) {
                        $day = $start->copy()->addDays($d);
                        $total = \App\Models\Booking::whereDate('start_time', $day->toDateString())->count();
                        $cancelled = \App\Models\Booking::whereDate('start_time', $day->toDateString())->where('status', \App\Models\Booking::STATUS_CANCELLED)->count();
                        $rate = $total > 0 ? round(($cancelled / $total) * 100, 2) : 0.0;
                        $trends[] = ['day' => $day->format('Y-m-d'), 'cancellation_rate' => $rate];
                    }
                    break;
                case 'month':
                    $daysInMonth = $date->daysInMonth;
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        $day = $date->copy()->day($d);
                        $total = \App\Models\Booking::whereDate('start_time', $day->toDateString())->count();
                        $cancelled = \App\Models\Booking::whereDate('start_time', $day->toDateString())->where('status', \App\Models\Booking::STATUS_CANCELLED)->count();
                        $rate = $total > 0 ? round(($cancelled / $total) * 100, 2) : 0.0;
                        $trends[] = ['day' => $day->format('Y-m-d'), 'cancellation_rate' => $rate];
                    }
                    break;
                case 'year':
                default:
                    $year = $date->year;
                    for ($m = 1; $m <= 12; $m++) {
                        $month = Carbon::create($year, $m, 1);
                        $total = \App\Models\Booking::whereYear('start_time', $year)
                            ->whereMonth('start_time', $m)
                            ->count();
                        $cancelled = \App\Models\Booking::whereYear('start_time', $year)
                            ->whereMonth('start_time', $m)
                            ->where('status', \App\Models\Booking::STATUS_CANCELLED)
                            ->count();
                        $rate = $total > 0 ? round(($cancelled / $total) * 100, 2) : 0.0;
                        $trends[] = ['month' => $month->format('Y-m'), 'cancellation_rate' => $rate];
                    }
                    break;
            }
            return $trends;
        } catch (\Exception $e) {
            \Log::error('Error fetching cancellation trends: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new DashboardException('Failed to retrieve cancellation trends.');
        }
    }
}
