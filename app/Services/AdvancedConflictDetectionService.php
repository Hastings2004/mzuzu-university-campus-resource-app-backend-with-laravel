<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Resource;
use App\Models\ResourceIssue;
use App\Models\Timetable;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AdvancedConflictDetectionService
{
    /**
     * Comprehensive conflict detection that goes beyond simple time overlaps.
     * 
     * @param int $resourceId
     * @param Carbon $startTime
     * @param Carbon $endTime
     * @param User $user
     * @param array $bookingData
     * @param int|null $excludeBookingId
     * @return array
     */
    public function detectAdvancedConflicts(
        int $resourceId, 
        Carbon $startTime, 
        Carbon $endTime, 
        User $user, 
        array $bookingData = [], 
        ?int $excludeBookingId = null
    ): array {
        $resource = Resource::find($resourceId);
        if (!$resource) {
            return [
                'has_conflicts' => true,
                'conflict_type' => 'resource_not_found',
                'message' => 'Resource not found.',
                'conflicts' => []
            ];
        }

        $conflicts = [];
        $conflictTypes = [];

        // 1. Check resource status and maintenance
        $maintenanceConflicts = $this->checkMaintenanceConflicts($resource, $startTime, $endTime);
        if (!empty($maintenanceConflicts)) {
            $conflicts = array_merge($conflicts, $maintenanceConflicts);
            $conflictTypes[] = 'maintenance';
        }

        // 2. Check shared equipment conflicts
        $equipmentConflicts = $this->checkSharedEquipmentConflicts($resource, $startTime, $endTime, $excludeBookingId);
        if (!empty($equipmentConflicts)) {
            $conflicts = array_merge($conflicts, $equipmentConflicts);
            $conflictTypes[] = 'shared_equipment';
        }

        // 3. Check resource dependencies
        $dependencyConflicts = $this->checkResourceDependencies($resource, $startTime, $endTime, $excludeBookingId);
        if (!empty($dependencyConflicts)) {
            $conflicts = array_merge($conflicts, $dependencyConflicts);
            $conflictTypes[] = 'resource_dependency';
        }

        // 4. Check timetable conflicts (fixed schedules)
        $timetableConflicts = $this->checkTimetableConflicts($resource, $startTime, $endTime);
        if (!empty($timetableConflicts)) {
            $conflicts = array_merge($conflicts, $timetableConflicts);
            $conflictTypes[] = 'timetable';
        }

        // 5. Check existing booking conflicts
        $bookingConflicts = $this->checkBookingConflicts($resource, $startTime, $endTime, $excludeBookingId);
        if (!empty($bookingConflicts)) {
            $conflicts = array_merge($conflicts, $bookingConflicts);
            $conflictTypes[] = 'booking';
        }

        // 6. Check user schedule conflicts
        $userScheduleConflicts = $this->checkUserScheduleConflicts($user, $startTime, $endTime, $excludeBookingId);
        if (!empty($userScheduleConflicts)) {
            $conflicts = array_merge($conflicts, $userScheduleConflicts);
            $conflictTypes[] = 'user_schedule';
        }

        // 7. Check resource capacity and availability
        $capacityConflicts = $this->checkCapacityConflicts($resource, $startTime, $endTime, $excludeBookingId);
        if (!empty($capacityConflicts)) {
            $conflicts = array_merge($conflicts, $capacityConflicts);
            $conflictTypes[] = 'capacity';
        }

        // 8. Check resource issues and problems
        $issueConflicts = $this->checkResourceIssues($resource, $startTime, $endTime);
        if (!empty($issueConflicts)) {
            $conflicts = array_merge($conflicts, $issueConflicts);
            $conflictTypes[] = 'resource_issue';
        }

        return [
            'has_conflicts' => !empty($conflicts),
            'conflict_types' => $conflictTypes,
            'conflicts' => $conflicts,
            'resource_status' => $resource->status,
            'resource_capacity' => $resource->capacity,
            'suggestions' => $this->generateConflictSuggestions($conflicts, $resource, $startTime, $endTime, $user)
        ];
    }

    /**
     * Check for maintenance conflicts.
     */
    private function checkMaintenanceConflicts(Resource $resource, Carbon $startTime, Carbon $endTime): array
    {
        $conflicts = [];

        // Check if resource is under maintenance
        if ($resource->status === 'maintenance') {
            $conflicts[] = [
                'type' => 'maintenance',
                'severity' => 'high',
                'message' => 'Resource is currently under maintenance.',
                'resource_id' => $resource->id,
                'resource_name' => $resource->name,
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'suggestion' => 'Please check back later or contact facilities management.'
            ];
        }

        // Check for scheduled maintenance during the requested time
        $maintenanceIssues = ResourceIssue::where('resource_id', $resource->id)
            ->where('status', 'in_progress')
            ->where('issue_type', 'maintenance')
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('created_at', [$startTime, $endTime])
                      ->orWhereBetween('resolved_at', [$startTime, $endTime])
                      ->orWhere(function ($q) use ($startTime, $endTime) {
                          $q->where('created_at', '<=', $startTime)
                            ->where('resolved_at', '>=', $endTime);
                      });
            })
            ->get();

        foreach ($maintenanceIssues as $issue) {
            $conflicts[] = [
                'type' => 'scheduled_maintenance',
                'severity' => 'medium',
                'message' => "Scheduled maintenance: {$issue->subject}",
                'resource_id' => $resource->id,
                'resource_name' => $resource->name,
                'issue_id' => $issue->id,
                'issue_description' => $issue->description,
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'suggestion' => 'Please select a different time slot or contact facilities management.'
            ];
        }

        return $conflicts;
    }

    /**
     * Check for shared equipment conflicts.
     * This handles scenarios where multiple resources share the same equipment.
     */
    private function checkSharedEquipmentConflicts(Resource $resource, Carbon $startTime, Carbon $endTime, ?int $excludeBookingId): array
    {
        $conflicts = [];

        // Get resources that share the same location or equipment
        $sharedResources = $this->getSharedResources($resource);
        
        foreach ($sharedResources as $sharedResource) {
            // Check for overlapping bookings on shared resources
            $overlappingBookings = Booking::where('resource_id', $sharedResource->id)
                ->whereIn('status', ['pending', 'approved', 'in_use'])
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where('start_time', '<', $endTime)
                          ->where('end_time', '>', $startTime);
                });

            if ($excludeBookingId) {
                $overlappingBookings->where('id', '!=', $excludeBookingId);
            }

            $bookings = $overlappingBookings->with('user')->get();

            foreach ($bookings as $booking) {
                $conflicts[] = [
                    'type' => 'shared_equipment',
                    'severity' => 'medium',
                    'message' => "Shared equipment conflict with {$sharedResource->name}",
                    'resource_id' => $resource->id,
                    'resource_name' => $resource->name,
                    'shared_resource_id' => $sharedResource->id,
                    'shared_resource_name' => $sharedResource->name,
                    'conflicting_booking_id' => $booking->id,
                    'conflicting_user' => $booking->user ? $booking->user->first_name . ' ' . $booking->user->last_name : 'Unknown',
                    'start_time' => $startTime->toDateTimeString(),
                    'end_time' => $endTime->toDateTimeString(),
                    'conflict_start' => $booking->start_time->toDateTimeString(),
                    'conflict_end' => $booking->end_time->toDateTimeString(),
                    'suggestion' => "Consider booking {$sharedResource->name} instead, or choose a different time slot."
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Check for resource dependencies.
     * This handles scenarios where one resource depends on another.
     */
    private function checkResourceDependencies(Resource $resource, Carbon $startTime, Carbon $endTime, ?int $excludeBookingId): array
    {
        $conflicts = [];

        // Get dependent resources (resources that depend on this one)
        $dependentResources = $this->getDependentResources($resource);
        
        foreach ($dependentResources as $dependentResource) {
            // Check if the dependent resource is available
            $dependentBookings = Booking::where('resource_id', $dependentResource->id)
                ->whereIn('status', ['pending', 'approved', 'in_use'])
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where('start_time', '<', $endTime)
                          ->where('end_time', '>', $startTime);
                });

            if ($excludeBookingId) {
                $dependentBookings->where('id', '!=', $excludeBookingId);
            }

            if ($dependentBookings->exists()) {
                $conflicts[] = [
                    'type' => 'resource_dependency',
                    'severity' => 'high',
                    'message' => "Dependent resource {$dependentResource->name} is not available",
                    'resource_id' => $resource->id,
                    'resource_name' => $resource->name,
                    'dependent_resource_id' => $dependentResource->id,
                    'dependent_resource_name' => $dependentResource->name,
                    'start_time' => $startTime->toDateTimeString(),
                    'end_time' => $endTime->toDateTimeString(),
                    'suggestion' => "Please ensure {$dependentResource->name} is available before booking this resource."
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Check for timetable conflicts (fixed schedules).
     */
    private function checkTimetableConflicts(Resource $resource, Carbon $startTime, Carbon $endTime): array
    {
        $conflicts = [];

        $dayOfWeek = $startTime->dayOfWeekIso; // 1 (Monday) through 7 (Sunday)

        $timetableConflicts = Timetable::where('room_id', $resource->id)
            ->where('day_of_week', $dayOfWeek)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('start_time', [$startTime->format('H:i:s'), $endTime->format('H:i:s')])
                    ->orWhereBetween('end_time', [$startTime->format('H:i:s'), $endTime->format('H:i:s')])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<=', $startTime->format('H:i:s'))
                          ->where('end_time', '>=', $endTime->format('H:i:s'));
                    });
            })
            ->get();

        foreach ($timetableConflicts as $timetable) {
            $conflicts[] = [
                'type' => 'timetable',
                'severity' => 'high',
                'message' => "Fixed schedule conflict: {$timetable->course_name}",
                'resource_id' => $resource->id,
                'resource_name' => $resource->name,
                'timetable_id' => $timetable->id,
                'course_name' => $timetable->course_name,
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'timetable_start' => $timetable->start_time,
                'timetable_end' => $timetable->end_time,
                'suggestion' => 'This time slot is reserved for scheduled classes. Please choose a different time.'
            ];
        }

        return $conflicts;
    }

    /**
     * Check for existing booking conflicts.
     */
    private function checkBookingConflicts(Resource $resource, Carbon $startTime, Carbon $endTime, ?int $excludeBookingId): array
    {
        $conflicts = [];

        $query = Booking::where('resource_id', $resource->id)
            ->whereIn('status', ['pending', 'approved', 'in_use'])
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            });

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        $conflictingBookings = $query->with('user')->get();

        foreach ($conflictingBookings as $booking) {
            $conflicts[] = [
                'type' => 'booking',
                'severity' => 'medium',
                'message' => 'Time slot already booked',
                'resource_id' => $resource->id,
                'resource_name' => $resource->name,
                'conflicting_booking_id' => $booking->id,
                'conflicting_user' => $booking->user ? $booking->user->first_name . ' ' . $booking->user->last_name : 'Unknown',
                'booking_type' => $booking->booking_type,
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'conflict_start' => $booking->start_time->toDateTimeString(),
                'conflict_end' => $booking->end_time->toDateTimeString(),
                'suggestion' => 'Please choose a different time slot or resource.'
            ];
        }

        return $conflicts;
    }

    /**
     * Check for user schedule conflicts.
     */
    private function checkUserScheduleConflicts(User $user, Carbon $startTime, Carbon $endTime, ?int $excludeBookingId): array
    {
        $conflicts = [];

        $userBookings = Booking::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved', 'in_use'])
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            });

        if ($excludeBookingId) {
            $userBookings->where('id', '!=', $excludeBookingId);
        }

        $conflictingUserBookings = $userBookings->with('resource')->get();

        foreach ($conflictingUserBookings as $booking) {
            $conflicts[] = [
                'type' => 'user_schedule',
                'severity' => 'low',
                'message' => 'You have another booking at this time',
                'user_id' => $user->id,
                'conflicting_booking_id' => $booking->id,
                'conflicting_resource' => $booking->resource ? $booking->resource->name : 'Unknown Resource',
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'conflict_start' => $booking->start_time->toDateTimeString(),
                'conflict_end' => $booking->end_time->toDateTimeString(),
                'suggestion' => 'Please cancel or modify your existing booking first.'
            ];
        }

        return $conflicts;
    }

    /**
     * Check for capacity conflicts.
     */
    private function checkCapacityConflicts(Resource $resource, Carbon $startTime, Carbon $endTime, ?int $excludeBookingId): array
    {
        $conflicts = [];

        if ($resource->capacity <= 1) {
            // For single-capacity resources, any conflict means no availability
            return $conflicts;
        }

        // Count existing bookings during the requested time
        $existingBookings = Booking::where('resource_id', $resource->id)
            ->whereIn('status', ['pending', 'approved', 'in_use'])
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            });

        if ($excludeBookingId) {
            $existingBookings->where('id', '!=', $excludeBookingId);
        }

        $bookingCount = $existingBookings->count();

        if ($bookingCount >= $resource->capacity) {
            $conflicts[] = [
                'type' => 'capacity',
                'severity' => 'medium',
                'message' => "Resource capacity ({$resource->capacity}) is fully utilized",
                'resource_id' => $resource->id,
                'resource_name' => $resource->name,
                'current_bookings' => $bookingCount,
                'capacity' => $resource->capacity,
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'suggestion' => 'Please choose a different time slot or resource with available capacity.'
            ];
        }

        return $conflicts;
    }

    /**
     * Check for resource issues and problems.
     */
    private function checkResourceIssues(Resource $resource, Carbon $startTime, Carbon $endTime): array
    {
        $conflicts = [];

        $activeIssues = ResourceIssue::where('resource_id', $resource->id)
            ->whereIn('status', ['reported', 'in_progress'])
            ->get();

        foreach ($activeIssues as $issue) {
            $conflicts[] = [
                'type' => 'resource_issue',
                'severity' => 'high',
                'message' => "Resource issue: {$issue->subject}",
                'resource_id' => $resource->id,
                'resource_name' => $resource->name,
                'issue_id' => $issue->id,
                'issue_type' => $issue->issue_type,
                'issue_description' => $issue->description,
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'suggestion' => 'Please contact facilities management or choose a different resource.'
            ];
        }

        return $conflicts;
    }

    /**
     * Get resources that share equipment or location with the given resource.
     */
    private function getSharedResources(Resource $resource): Collection
    {
        // This is a simplified implementation. In a real system, you might have
        // a more complex relationship table for shared equipment.
        
        return Resource::where('location', $resource->location)
            ->where('id', '!=', $resource->id)
            ->where('category', $resource->category)
            ->get();
    }

    /**
     * Get resources that depend on the given resource.
     */
    private function getDependentResources(Resource $resource): Collection
    {
        // This is a simplified implementation. In a real system, you might have
        // a dependency table or configuration.
        
        // For now, return empty collection
        return collect();
    }

    /**
     * Generate suggestions for resolving conflicts.
     */
    private function generateConflictSuggestions(array $conflicts, Resource $resource, Carbon $startTime, Carbon $endTime, User $user): array
    {
        $suggestions = [];

        // 1. Alternative time slot for the same resource
        $nextSlot = $this->findNextAvailableSlot($resource, $startTime, $endTime, $user);
        if ($nextSlot) {
            $suggestions[] = [
                'type' => 'alternative_time',
                'resource_id' => $resource->id,
                'resource_name' => $resource->name,
                'start_time' => $nextSlot['start_time'],
                'end_time' => $nextSlot['end_time'],
                'preference_score' => 0.9,
                'message' => 'Next available slot for this resource.'
            ];
        }

        // 2. Alternative resources for the same time
        $alternativeResources = $this->getAlternativeResources($resource, $startTime, $endTime, $user);
        foreach ($alternativeResources as $alt) {
            $suggestions[] = [
                'type' => 'alternative_resource',
                'resource_id' => $alt['resource_id'],
                'resource_name' => $alt['resource_name'],
                'start_time' => $startTime->toIso8601String(),
                'end_time' => $endTime->toIso8601String(),
                'preference_score' => 0.8,
                'message' => 'Similar resource available for your requested time.'
            ];
        }

        // 3. Fallback generic suggestion
        if (empty($suggestions)) {
            $suggestions[] = [
                'type' => 'contact_support',
                'message' => 'Contact support if you need assistance with booking conflicts.',
                'priority' => 'low'
            ];
        }

        return $suggestions;
    }

    /**
     * Find the next available slot for a resource after a conflict.
     */
    private function findNextAvailableSlot(Resource $resource, Carbon $startTime, Carbon $endTime, User $user, $slotDurationMinutes = null)
    {
        $slotDurationMinutes = $slotDurationMinutes ?? $endTime->diffInMinutes($startTime);
        $searchStart = $endTime->copy();
        $searchEnd = $searchStart->copy()->addDays(7); // Search up to 7 days ahead

        while ($searchStart->lt($searchEnd)) {
            $candidateEnd = $searchStart->copy()->addMinutes($slotDurationMinutes);
            $conflicts = $this->detectAdvancedConflicts($resource->id, $searchStart, $candidateEnd, $user);

            if (!$conflicts['has_conflicts']) {
                return [
                    'start_time' => $searchStart->toIso8601String(),
                    'end_time' => $candidateEnd->toIso8601String(),
                ];
            }
            $searchStart->addMinutes(30); // Try next 30-minute slot
        }
        return null;
    }

    /**
     * Get alternative resources based on features and availability.
     */
    public function getAlternativeResources(Resource $originalResource, Carbon $startTime, Carbon $endTime, User $user): array
    {
        $alternatives = [];

        // Find resources with similar features
        $similarResources = Resource::where('category', $originalResource->category)
            ->where('id', '!=', $originalResource->id)
            ->where('status', 'available')
            ->get();

        foreach ($similarResources as $resource) {
            $conflicts = $this->detectAdvancedConflicts($resource->id, $startTime, $endTime, $user);
            
            if (!$conflicts['has_conflicts']) {
                $alternatives[] = [
                    'resource_id' => $resource->id,
                    'resource_name' => $resource->name,
                    'location' => $resource->location,
                    'capacity' => $resource->capacity,
                    'category' => $resource->category,
                    'reason' => 'Similar resource with no conflicts'
                ];
            }
        }

        return $alternatives;
    }
} 