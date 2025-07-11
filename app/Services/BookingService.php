<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Resource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Notifications\BookingApproved;
use App\Notifications\BookingRejected;
use App\Notifications\BookingPreempted;
use Illuminate\Support\Str;
use App\Exceptions\BookingException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // Import Storage facade

class BookingService
{
    // Define constants for booking rules and statuses
    const MIN_DURATION_MINUTES = 30;
    const MAX_DURATION_HOURS = 8;
    const MAX_ACTIVE_BOOKINGS = 5;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';
    const STATUS_IN_USE = 'in_use';
    const STATUS_PREEMPTED = 'preempted';
    const STATUS_EXPIRED = 'expired';

    /**
     * Assigns priority level based on user type and booking type.
     * Higher integer means higher priority.
     *
     * @param User $user
     * @param string $bookingType
     * @return int
     */
    private function determinePriority(User $user, string $bookingType): int
    {
        // Determine user type/role
        $userType = $user->user_type ?? ($user->roles->first() ? $user->roles->first()->name : 'student'); // Default to 'student' if not found

        $priority = 0; // Base priority

        switch ($bookingType) {
            case 'university_activity':
                $priority = 6; 
                break;
            case 'class':
                $priority = 5; 
                break;
            case 'staff_meeting':
                $priority = 4; 
                break;
            case 'church_meeting':
                $priority = 3;
                break;
            case 'student_meeting':
                $priority = 2; 
                break;
            default:
                $priority = 1; 
                break;
        }

        if (strtolower($userType) === 'admin') {
            $priority += 3; 
        } elseif (strtolower($userType) === 'staff' || strtolower($userType) === 'lecturer') {
            $priority += 2; 
        }

        return $priority;
    }

    /**
     * Finds conflicting bookings for a given time slot and resource.
     *
     * @param int $resourceId
     * @param Carbon $startTime
     * @param Carbon $endTime
     * @param int|null $excludeBookingId
     * @return \Illuminate\Support\Collection<Booking>
     */
    public function findConflictingBookings(int $resourceId, Carbon $startTime, Carbon $endTime, ?int $excludeBookingId = null): \Illuminate\Support\Collection
    {
        // Get all active bookings for this resource that could potentially conflict
        $query = Booking::where('resource_id', $resourceId)
            ->whereIn('status', [
                self::STATUS_PENDING, 
                self::STATUS_APPROVED, 
                self::STATUS_IN_USE,
            ])
            ->where(function ($q) use ($startTime, $endTime) {
                // Check for any overlap between the requested time slot and existing bookings
                $q->where(function ($subQ) use ($startTime, $endTime) {
                    // Case 1: Existing booking starts before new booking ends AND ends after new booking starts
                    $subQ->where('start_time', '<', $endTime)
                          ->where('end_time', '>', $startTime);
                });
            });

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        // Eager load the user and their role to access user_type/role name and first_name, last_name, email for notifications and priority
        return $query->with(['user' => function ($query) {
            $query->select('id', 'first_name', 'last_name', 'email', 'user_type')->with('roles:id,name');
        }])->get();
    }

    /**
     * Validates booking start and end times against business rules.
     *
     * @param Carbon $startTime
     * @param Carbon $endTime
     * @throws BookingException
     * @return void
     */
    private function validateBookingTimes(Carbon $startTime, Carbon $endTime): void
    {
        // Start time must be in the future (allowing for a small buffer to handle immediate requests)
        if ($startTime->lt(Carbon::now()->subMinutes(1))) {
            throw new BookingException('Booking start time must be in the future.');
        }

        // End time must be strictly after start time
        if ($endTime->lte($startTime)) {
            throw new BookingException('End time must be greater than start time.');
        }

        // Check duration constraints
        $durationInMinutes = $startTime->diffInMinutes($endTime);

        if ($durationInMinutes < self::MIN_DURATION_MINUTES) {
            throw new BookingException('Booking duration must be at least ' . self::MIN_DURATION_MINUTES . ' minutes.');
        }
        // Add max duration check if it's desired based on MAX_DURATION_HOURS constant
        // if ($durationInMinutes > (self::MAX_DURATION_HOURS * 60)) {
        //     throw new BookingException('Booking duration cannot exceed ' . self::MAX_DURATION_HOURS . ' hours.');
        // }

        // Validate that booking is not during restricted hours (11 PM to 4 AM)
        //$this->validateRestrictedHours($startTime, $endTime);
    }

    /**
     * Validates that bookings are not made during restricted hours (11 PM to 4 AM).
     *
     * @param Carbon $startTime
     * @param Carbon $endTime
     * @throws BookingException
     * @return void
     */
    private function validateRestrictedHours(Carbon $startTime, Carbon $endTime): void
    {
        // Define restricted hours (11 PM to 4 AM)
        $restrictedStartHour = 23; 
        $restrictedEndHour = 4;    

        // Check if the booking overlaps with restricted hours
        $startHour = $startTime->hour;
        $endHour = $endTime->hour;

        if ($restrictedStartHour > $restrictedEndHour) {
            if (($startHour >= $restrictedStartHour || $startHour < $restrictedEndHour) ||
                ($endHour > $restrictedStartHour || $endHour <= $restrictedEndHour) ||
                ($startHour < $restrictedEndHour && $endHour > $restrictedStartHour)) {
                throw new BookingException('Bookings are not allowed between 11:00 PM and 4:00 AM.');
            }
        } else {
            if (($startHour >= $restrictedStartHour && $startHour < $restrictedEndHour) ||
                ($endHour > $restrictedStartHour && $endHour <= $restrictedEndHour) ||
                ($startHour < $restrictedStartHour && $endHour > $restrictedEndHour)) {
                throw new BookingException('Bookings are not allowed between 11:00 PM and 4:00 AM.');
            }
        }
    }

    /**
     * Validates if a user has exceeded their maximum active booking limit.
     *
     * @param User $user
     * @throws BookingException
     * @return void
     */
    private function validateUserBookingLimit(User $user): void
    {
        $activeBookingsCount = $user->bookings()
            ->whereIn('status', [self::STATUS_APPROVED, self::STATUS_PENDING, self::STATUS_IN_USE])
            ->where('end_time', '>', Carbon::now()) // Only count bookings that are still active or in the future
            ->count();

        if ($activeBookingsCount >= self::MAX_ACTIVE_BOOKINGS) {
            throw new BookingException('You have reached the maximum limit of ' . self::MAX_ACTIVE_BOOKINGS . ' active bookings.');
        }
    }

    /**
     * Validates resource existence and active status.
     *
     * @param int $resourceId
     * @return Resource
     * @throws BookingException
     */
    private function validateResource(int $resourceId): Resource
    {
        $resource = Resource::find($resourceId);

        if (!$resource) {
            throw new BookingException('Resource not found.');
        }

        if (!$resource->is_active) {
            throw new BookingException('The selected resource is currently not active.');
        }

        return $resource;
    }

    /**
     * Handles the creation and scheduling of a new booking request with priority logic.
     * This method contains the core "optimization" (priority-based preemption) algorithm.
     *
     * @param array $data Contains resource_id, start_time, end_time, purpose, booking_type, (optional) supporting_document
     * @param User $user The user making the booking
     * @return array Contains 'success', 'message', 'booking' (if successful), 'status_code'
     */
    public function createBooking(array $data, User $user): array
    {
        DB::beginTransaction();

        try {
            $resource = $this->validateResource($data['resource_id']);
            $startTime = Carbon::parse($data['start_time']);
            $endTime = Carbon::parse($data['end_time']);

            // Apply core business rule validations
            $this->validateBookingTimes($startTime, $endTime);
            $this->validateUserBookingLimit($user);
            //$this->validateRestrictedHours($startTime, $endTime);
            $newBookingPriority = $this->determinePriority($user, $data['booking_type']);

            // Handle supporting document upload
            $documentPath = null;
            if (isset($data['supporting_document']) && $data['supporting_document'] instanceof \Illuminate\Http\UploadedFile) {
                $document = $data['supporting_document'];
                $documentName = time() . '_' . Str::random(10) . '.' . $document->getClientOriginalExtension();
                $documentPath = Storage::putFileAs('public/booking_documents', $document, $documentName);
                // Strip 'public/' prefix if present
                if (strpos($documentPath, 'public/') === 0) {
                    $documentPath = substr($documentPath, strlen('public/'));
                }
            }

            // Check for conflicting bookings
            $conflictingBookings = $this->findConflictingBookings(
                $data['resource_id'],
                $startTime,
                $endTime
            );

            // Log conflict detection for debugging
            Log::info('Conflict detection for booking creation', [
                'resource_id' => $data['resource_id'],
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'conflicts_found' => $conflictingBookings->count(),
                'user_id' => $user->id,
                'booking_type' => $data['booking_type'],
                'new_priority' => $newBookingPriority
            ]);

            $preemptableConflicts = collect();
            $nonPreemptableConflicts = collect();

            foreach ($conflictingBookings as $conflict) {
                if (!$conflict->user) {
                    Log::warning("Conflicting booking {$conflict->id} has no associated user. Skipping priority determination for this conflict.");
                    $nonPreemptableConflicts->push($conflict); 
                    continue;
                }
                $conflictPriority = $this->determinePriority($conflict->user, $conflict->booking_type);

                Log::info('Conflict priority comparison', [
                    'conflict_id' => $conflict->id,
                    'conflict_user' => $conflict->user->first_name . ' ' . $conflict->user->last_name,
                    'conflict_booking_type' => $conflict->booking_type,
                    'conflict_priority' => $conflictPriority,
                    'new_priority' => $newBookingPriority,
                    'is_preemptable' => $newBookingPriority > $conflictPriority
                ]);

                if ($newBookingPriority > $conflictPriority) {
                    $preemptableConflicts->push($conflict);
                } else {
                    $nonPreemptableConflicts->push($conflict);
                }
            }

            // Check if resource capacity is exceeded by non-preemptable bookings
            if ($resource->capacity == 1 && $nonPreemptableConflicts->isNotEmpty()) {
                // Instead of throwing, return suggestions
                $suggestions = $this->getBookingSuggestions($user, $resource, $startTime, $endTime);
                // Notify user with suggestions
                $user->notify(new \App\Notifications\BookingRejected(null, 'The resource is not available due to a higher or equal priority booking.', $suggestions));
                return [
                    'success' => false,
                    'message' => 'The resource is not available due to a higher or equal priority booking. Here are some alternatives.',
                    'booking' => null,
                    'status_code' => 409,
                    'suggestions' => $suggestions
                ];
            }

            // For resources with capacity > 1, check if the combined count of new booking + non-preemptable conflicts exceeds capacity
            if ($resource->capacity > 1 && ($nonPreemptableConflicts->count() + 1 > $resource->capacity)) {
                // Instead of throwing, return suggestions
                $suggestions = $this->getBookingSuggestions($user, $resource, $startTime, $endTime);
                // Notify user with suggestions
                $user->notify(new \App\Notifications\BookingRejected(null, 'Resource capacity is fully booked by higher or equal priority bookings.', $suggestions));
                return [
                    'success' => false,
                    'message' => 'Resource capacity is fully booked by higher or equal priority bookings. Here are some alternatives.',
                    'booking' => null,
                    'status_code' => 409,
                    'suggestions' => $suggestions
                ];
            }

            // All checks passed, proceed with booking.
                
            foreach ($preemptableConflicts as $preemptedBooking) {
                $preemptedBooking->status = self::STATUS_PREEMPTED; 
                $preemptedBooking->cancellation_reason = 'Preempted by higher priority booking (Ref: ' . $this->generateBookingReference() . ')';
                $preemptedBooking->cancelled_at = Carbon::now();
                $preemptedBooking->save();

                // Notify the user whose booking was preempted
                if ($preemptedBooking->user) {
                    $preemptedBooking->user->notify(new \App\Notifications\BookingPreempted($preemptedBooking));
                }
            }

            // Create the new booking
            $defaultApproved = "pending";

            if($resource->special_approval == 'yes'){
                $defaultApproved = "pending";
            } elseif ($resource->special_approval == 'no'){
                $defaultApproved = "approved";
            }else{
                $defaultApproved = "pending";
            }

            
            $booking = $user->bookings()->create([
                "booking_reference" => $this->generateBookingReference(),
                "resource_id" => $data['resource_id'],
                "start_time" => $startTime,
                "end_time" => $endTime,      
                "status" => $defaultApproved,          
                "purpose" => $data['purpose'] ?? null,
                "booking_type" => $data['booking_type'],
                "priority" => $newBookingPriority,
                "supporting_document_path" => $documentPath, 
            ]);

            if ($user) {
                $user->notify(new \App\Notifications\BookingCreated($booking));
            }

            // Notify all admins
            $adminUsers = \App\Models\User::where('user_type', 'admin')->get();
            foreach ($adminUsers as $admin) {
                $admin->notify(new \App\Notifications\BookingCreatedAdmin($booking));
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Booking created successfully.',
                'booking' => $booking->load('resource', 'user'), 
                'status_code' => 201
            ];
        } catch (BookingException $e) {
            DB::rollBack();
            Log::warning('Booking validation failed: ' . $e->getMessage(), ['user_id' => $user->id ?? 'guest']);
            return ['success' => false, 'message' => $e->getMessage(), 'booking' => null, 'status_code' => 400]; // Bad Request for validation errors
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Booking creation failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString(), 'user_id' => $user->id ?? 'guest']);
            return ['success' => false, 'message' => 'An unexpected error occurred while creating the booking.', 'booking' => null, 'status_code' => 500];
        }
    }

    /**
     * Update an existing booking with priority scheduling considerations.
     *
     * @param Booking $booking The booking to update
     * @param array $data New data for the booking (start_time, end_time, purpose, booking_type, (optional) supporting_document)
     * @param User $user The user performing the update (for priority recalculation)
     * @return array Contains 'success', 'message', 'booking' (if successful), 'status_code'
     */
    public function updateBooking(Booking $booking, array $data, User $user): array
    {
        DB::beginTransaction();
        try {
            // Cannot modify bookings that have already started or are in the past
            // if ($booking->start_time->lt(Carbon::now())) {
            //      throw new BookingException('Cannot modify bookings that have already started or are in the past.');
            // }
            // Cannot modify cancelled or preempted bookings
            if (in_array($booking->status, [self::STATUS_CANCELLED, self::STATUS_PREEMPTED, self::STATUS_COMPLETED])) {
                throw new BookingException('Cannot modify a ' . $booking->status . ' booking.');
            }

            $startTime = isset($data['start_time']) ? Carbon::parse($data['start_time']) : $booking->start_time;
            $endTime = isset($data['end_time']) ? Carbon::parse($data['end_time']) : $booking->end_time;
            $resource = $this->validateResource($booking->resource_id); // Re-validate resource for safety
            $bookingType = $data['booking_type'] ?? $booking->booking_type;

            // Apply core business rule validations if times are being changed
            if (isset($data['start_time']) || isset($data['end_time'])) {
                $this->validateBookingTimes($startTime, $endTime);
            }

            // Handle supporting document upload during update
            $documentPath = $booking->supporting_document_path; // Keep existing path by default
            if (isset($data['supporting_document'])) {
                if ($data['supporting_document'] instanceof \Illuminate\Http\UploadedFile) {
                    // Delete old document if it exists
                    if ($booking->supporting_document_path) {
                        Storage::delete('public/' . ltrim($booking->supporting_document_path, '/'));
                    }
                    $document = $data['supporting_document'];
                    $documentName = time() . '_' . Str::random(10) . '.' . $document->getClientOriginalExtension();
                    $documentPath = Storage::putFileAs('public/booking_documents', $document, $documentName);
                    // Strip 'public/' prefix if present
                    if (strpos($documentPath, 'public/') === 0) {
                        $documentPath = substr($documentPath, strlen('public/'));
                    }
                } elseif (is_null($data['supporting_document'])) {
                    // If frontend sends null, it means document should be removed
                    if ($booking->supporting_document_path) {
                        Storage::delete('public/' . ltrim($booking->supporting_document_path, '/'));
                    }
                    $documentPath = null;
                }
            }


            // Determine new priority level for the updated booking
            $newPriorityLevel = $this->determinePriority($user, $bookingType);

            // Find conflicts, EXCLUDING the current booking being updated from the conflict check
            $conflictingBookings = $this->findConflictingBookings(
                $booking->resource_id,
                $startTime,
                $endTime,
                $booking->id 
            );

            $preemptableConflicts = collect();
            $nonPreemptableConflicts = collect();

            foreach ($conflictingBookings as $conflict) {
                if (!$conflict->user) {
                    Log::warning("Conflicting booking {$conflict->id} has no associated user during update. Skipping priority determination for this conflict.");
                    $nonPreemptableConflicts->push($conflict); // Treat as non-preemptable if user is missing
                    continue;
                }
                // A conflict is preemptable if the UPDATED booking's priority is strictly higher than the existing conflict's priority
                if ($newPriorityLevel > $this->determinePriority($conflict->user, $conflict->booking_type)) {
                    $preemptableConflicts->push($conflict);
                } else {
                    $nonPreemptableConflicts->push($conflict);
                }
            }

            // Check if resource capacity is exceeded by non-preemptable bookings (after considering preemption)
            if ($resource->capacity == 1 && $nonPreemptableConflicts->isNotEmpty()) {
                throw new BookingException('Cannot update: a higher or equal priority booking already exists for this time slot.');
            }
            if ($resource->capacity > 1 && ($nonPreemptableConflicts->count() + 1 > $resource->capacity)) { // +1 for the updated booking itself
                throw new BookingException('Cannot update: resource capacity is fully booked by higher or equal priority bookings.');
            }

            // Proceed with update
            // Preempt lower priority bookings
            foreach ($preemptableConflicts as $preemptedBooking) {
                $preemptedBooking->status = self::STATUS_PREEMPTED;
                $preemptedBooking->cancellation_reason = 'Preempted by updated higher priority booking (Ref: ' . $booking->booking_reference . ')';
                $preemptedBooking->cancelled_at = Carbon::now();
                $preemptedBooking->save();
                if ($preemptedBooking->user) {
                    $preemptedBooking->user->notify(new \App\Notifications\BookingPreempted($preemptedBooking));
                }
            }

            // Update the booking details
            $booking->fill($data); // Fill with validated data
            $booking->start_time = $startTime; // Ensure carbon instances are used
            $booking->end_time = $endTime;
            $booking->priority = $newPriorityLevel; // Update priority level
            $booking->booking_type = $bookingType;
            $booking->status = self::STATUS_APPROVED; // Re-approve if it successfully fits/preempted
            $booking->supporting_document_path = $documentPath; // Save document path
            $booking->save();

            DB::commit();

            return [
                'success' => true,
                'message' => 'Booking updated successfully and conflicts resolved by priority.',
                'booking' => $booking->fresh()->load('resource', 'user'),
                'status_code' => 200
            ];
        } catch (BookingException $e) {
            DB::rollBack();
            Log::warning('Booking update validation failed: ' . $e->getMessage(), ['user_id' => $user->id ?? 'guest']);
            return ['success' => false, 'message' => $e->getMessage(), 'booking' => null, 'status_code' => 400];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Booking update failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString(), 'user_id' => $user->id ?? 'guest']);
            return ['success' => false, 'message' => 'An unexpected error occurred while updating the booking.', 'booking' => null, 'status_code' => 500];
        }
    }

    /**
     * Cancel a booking.
     *
     * @param Booking $booking
     * @return array
     */
    public function cancelBooking(Booking $booking): array
    {
        DB::beginTransaction();
        try {
            if (in_array($booking->status, [self::STATUS_CANCELLED, self::STATUS_PREEMPTED, self::STATUS_COMPLETED])) {
                throw new BookingException('Cannot cancel a booking that is already ' . $booking->status . '.');
            }
            // Allow cancellation of future or ongoing bookings only
            if ($booking->end_time->lt(Carbon::now())) {
                throw new BookingException('Cannot cancel bookings that have already completed.');
            }

            $booking->update([
                'status' => self::STATUS_CANCELLED,
                'cancelled_at' => Carbon::now(),
                'cancellation_reason' => 'User cancelled booking.' // Default reason, can be extended via request
            ]);

            // Notify user of cancellation
            if ($booking->user) {
                $booking->user->notify(new \App\Notifications\BookingRejected($booking, 'User cancelled booking.'));
            }

            DB::commit();
            return [
                'success' => true,
                'message' => 'Booking cancelled successfully.',
                'booking' => $booking->fresh(),
                'status_code' => 200
            ];
        } catch (BookingException $e) {
            DB::rollBack();
            Log::warning('Booking cancellation failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'status_code' => 400];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Booking cancellation failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ['success' => false, 'message' => 'An unexpected error occurred while cancelling the booking.', 'status_code' => 500];
        }
    }

    /**
     * Cancel multiple bookings for a user.
     *
     * @param array $bookingIds
     * @param int $userId
     * @param string|null $reason
     * @return array
     */
    public function cancelMultipleBookings(array $bookingIds, int $userId, ?string $reason = null): array
    {
        DB::beginTransaction();
        $cancelledCount = 0;
        $errors = [];

        foreach ($bookingIds as $bookingId) {
            try {
                $booking = Booking::where('id', $bookingId)->where('user_id', $userId)->first();
                if (!$booking) {
                    $errors[] = "Booking #{$bookingId} not found or does not belong to user.";
                    continue;
                }
                // Use the single cancellation method to ensure all rules and notifications apply
                $result = $this->cancelBooking($booking);
                if ($result['success']) {
                    $cancelledCount++;
                } else {
                    $errors[] = "Booking #{$bookingId} cannot be cancelled: " . $result['message'];
                }
            } catch (\Exception $e) {
                $errors[] = "Booking #{$bookingId} encountered an error: " . $e->getMessage();
                Log::error('Error cancelling multiple bookings: ' . $e->getMessage(), ['booking_id' => $bookingId, 'user_id' => $userId, 'trace' => $e->getTraceAsString()]);
            }
        }
        // Commit only if all individual cancellations were successful, otherwise rollback the whole batch.
        // Or, more practically for multiple operations, if one fails, the others might still succeed.
        // The current implementation where `cancelBooking` handles its own transaction and commits immediately
        // means this outer transaction might not be strictly necessary for atomicity of individual cancellations,
        // but it could be useful if you wanted to track successes/failures in a single report and roll back everything
        // if even one fails (which is not what the current `errors` array implies).
        // Given the current structure, it's safer to commit here as individual `cancelBooking` calls already manage their DB.
        DB::commit();

        return [
            'cancelled_count' => $cancelledCount,
            'total_requested' => count($bookingIds),
            'errors' => $errors
        ];
    }

    /**
     * Get cancellation statistics for a user.
     *
     * @param int $userId
     * @return object
     */
    public function getCancellationStats(int $userId): object
    {
        $stats = Booking::where('user_id', $userId)
            ->selectRaw('
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled_bookings,
                SUM(CASE WHEN status = ? AND cancelled_at >= ? THEN 1 ELSE 0 END) as recent_cancellations
            ', [
                self::STATUS_CANCELLED,
                self::STATUS_CANCELLED,
                Carbon::now()->subDays(30)
            ])
            ->first();

        return $stats;
    }

    private function generateBookingReference(): string
    {
        do {
            $reference = 'MZUNI-RBA-' . now()->format('dmHi') . '-' . strtoupper(Str::random(6));
        } while (Booking::where('booking_reference', $reference)->exists());

        return $reference;
    }
    /**
     * Check resource availability based on conflicts (without creating a booking).
     *
     * @param int $resourceId
     * @param Carbon $startTime
     * @param Carbon $endTime
     * @param int|null $excludeBookingId
     * @return array
     */
    public function checkAvailabilityStatus(int $resourceId, Carbon $startTime, Carbon $endTime, ?int $excludeBookingId = null): array
    {
        try {
            $resource = $this->validateResource($resourceId);

            // Basic time validation for the check
            $this->validateBookingTimes($startTime, $endTime);

            $conflictingBookings = $this->findConflictingBookings(
                $resourceId,
                $startTime,
                $endTime,
                $excludeBookingId
            );

            $conflictCount = $conflictingBookings->count();

            if ($conflictCount > 0) {
                return [
                    'available' => false,
                    'hasConflict' => true,
                    'message' => 'Resource is already fully booked during this time.',
                    'conflicts' => $conflictingBookings->map(function ($booking) {
                        return [
                            'id' => $booking->id,
                            'start_time' => $booking->start_time->format('Y-m-d H:i'),
                            'end_time' => $booking->end_time->format('Y-m-d H:i'),                           
                            'user' => $booking->user ? ($booking->user->first_name . ' ' . $booking->user->last_name) : 'Unknown',
                            'priority_level' => $booking->user ? $this->determinePriority($booking->user, $booking->booking_type) : null,
                            'status' => $booking->status, // Include status
                        ];
                    })
                ];
            }

            if ($resource->capacity > 1 && $conflictCount >= $resource->capacity) {
                return [
                    'available' => false,
                    'hasConflict' => true,
                    'message' => sprintf(
                        'Resource capacity (%d) is fully booked for the selected time period.',
                        $resource->capacity
                    ),
                    'conflicts' => $conflictingBookings->map(function ($booking) {
                        return [
                            'id' => $booking->id,
                            'start_time' => $booking->start_time->format('Y-m-d H:i'),
                            'end_time' => $booking->end_time->format('Y-m-d H:i'),
                            'user' => $booking->user ? ($booking->user->first_name . ' ' . $booking->user->last_name) : 'Unknown',
                            'priority_level' => $booking->user ? $this->determinePriority($booking->user, $booking->booking_type) : null,
                            'status' => $booking->status, // Include status
                        ];
                    })
                ];
            }

            return [
                'available' => true,
                'hasConflict' => false,
                'message' => 'Time slot is available.',
                'conflicts' => $conflictingBookings->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'start_time' => $booking->start_time->format('Y-m-d H:i'),
                        'end_time' => $booking->end_time->format('Y-m-d H:i'),
                        'user' => $booking->user ? ($booking->user->first_name . ' ' . $booking->user->last_name) : 'Unknown',
                        'priority_level' => $booking->user ? $this->determinePriority($booking->user, $booking->booking_type) : null,
                        'status' => $booking->status,
                    ];
                })
            ];
        } catch (BookingException $e) {
            Log::warning('Availability check failed: ' . $e->getMessage());
            return ['available' => false, 'hasConflict' => true, 'message' => $e->getMessage(), 'conflicts' => []];
        } catch (\Exception $e) {
            Log::error('Availability check failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ['available' => false, 'hasConflict' => true, 'message' => 'An unexpected error occurred during availability check.', 'conflicts' => []];
        }
    }

    /**
     * Marks bookings as 'expired' if their end time has passed.
     * This function should ideally be run as a scheduled task (e.g., daily or hourly).
     *
     * @return int The number of bookings that were marked as expired.
     */
    public function markExpiredBookings(): int
    {
        $now = Carbon::now();

        // Query for bookings that have ended and are not already in a final state
        $updatedCount = Booking::where('end_time', '<', $now)
            ->whereNotIn('status', [
                self::STATUS_CANCELLED,
                self::STATUS_PREEMPTED,
                self::STATUS_COMPLETED,
                self::STATUS_EXPIRED 
            ])
            ->update(['status' => self::STATUS_EXPIRED]);

        if ($updatedCount > 0) {
            Log::info("{$updatedCount} bookings marked as expired.");
        }

        return $updatedCount;
    }

    /**
     * Get user's bookings with pagination and filtering
     */
    public function getUserBookings(Request $request)
    {
        try {
            $perPage = min($request->get('per_page', 10), 50); // Limit per page
            $status = $request->get('status');
            $upcoming = $request->get('upcoming', false);

            $query = $request->user()->bookings()
                ->with(['resource:id,name,location'])
                ->select(['id', 'resource_id', 'start_time', 'end_time', 'status', 'purpose', 'created_at']);

            if ($status) {
                $query->where('status', $status);
            }

            if ($upcoming) {
                $query->where('start_time', '>', now());
            }

            $bookings = $query->orderBy('start_time', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'bookings' => $bookings->items(),
                'pagination' => [
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                    'per_page' => $bookings->perPage(),
                    'total' => $bookings->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch user bookings', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bookings'
            ], 500);
        }
    }

    /**
     * Check if a resource is available for the given time slot.
     */
    public function isResourceAvailable($resourceId, $start, $end, $ignoreBookingId = null)
    {
        $query = Booking::where('resource_id', $resourceId)
            ->whereIn('status', [
                self::STATUS_PENDING, 
                self::STATUS_APPROVED, 
                self::STATUS_IN_USE,
                'in user' // Include old status for backward compatibility
            ])
            ->where(function ($q) use ($start, $end) {
                // Check for any overlap between the requested time slot and existing bookings
                $q->where(function ($subQ) use ($start, $end) {
                    // Case 1: Existing booking starts before new booking ends AND ends after new booking starts
                    $subQ->where('start_time', '<', $end)
                          ->where('end_time', '>', $start);
                });
            });
        
        if ($ignoreBookingId) {
            $query->where('id', '!=', $ignoreBookingId);
        }
        
        return !$query->exists();
    }

    /**
     * Suggest alternative time slots for a resource.
     */
    public function suggestNearbySlots($resourceId, $start, $end, $intervals = [15, 30])
    {
        $suggestions = [];
        foreach ($intervals as $minutes) {
            $earlierStart = Carbon::parse($start)->subMinutes($minutes);
            $earlierEnd = Carbon::parse($end)->subMinutes($minutes);
            if ($this->isResourceAvailable($resourceId, $earlierStart, $earlierEnd)) {
                $suggestions[] = [
                    'resource_id' => $resourceId,
                    'start_time' => $earlierStart->toDateTimeString(),
                    'end_time' => $earlierEnd->toDateTimeString(),
                    'type' => 'shifted_earlier'
                ];
            }
            $laterStart = Carbon::parse($start)->addMinutes($minutes);
            $laterEnd = Carbon::parse($end)->addMinutes($minutes);
            if ($this->isResourceAvailable($resourceId, $laterStart, $laterEnd)) {
                $suggestions[] = [
                    'resource_id' => $resourceId,
                    'start_time' => $laterStart->toDateTimeString(),
                    'end_time' => $laterEnd->toDateTimeString(),
                    'type' => 'shifted_later'
                ];
            }
        }
        return $suggestions;
    }

    /**
     * Suggest alternative resources based on features and category similarity.
     * Enhanced to consider both category and features of the requested resource.
     */
    public function suggestSimilarResources($resource, $start, $end)
    {
        $thirtyDaysAgo = now()->subDays(30);
        
        // Get the features of the requested resource
        $requestedFeatures = $resource->features()->pluck('features.id')->toArray();
        
        // Find alternative resources based on multiple criteria
        $alternativeResources = Resource::where('id', '!=', $resource->id)
            ->where('is_active', true)
            ->where(function ($query) use ($resource, $requestedFeatures) {
                // Same category
                $query->where('category', $resource->category)
                    // OR similar features (at least 50% feature match)
                    ->orWhereHas('features', function ($featureQuery) use ($requestedFeatures) {
                        if (!empty($requestedFeatures)) {
                            $featureQuery->whereIn('features.id', $requestedFeatures);
                        }
                    });
            })
            ->with('features') // Eager load features for scoring
            ->get();
        
        $usageStats = [];
        $suggestions = [];
        
        foreach ($alternativeResources as $res) {
            // Count bookings in the last 30 days
            $usageStats[$res->id] = $res->bookings()
                ->where('start_time', '>=', $thirtyDaysAgo)
                ->count();
            
            // Calculate feature similarity score
            $resFeatures = $res->features()->pluck('features.id')->toArray();
            $featureMatchCount = count(array_intersect($requestedFeatures, $resFeatures));
            $featureSimilarityScore = !empty($requestedFeatures) ? ($featureMatchCount / count($requestedFeatures)) * 100 : 0;
            
            // Check availability
            if ($this->isResourceAvailable($res->id, $start, $end)) {
                $suggestions[] = [
                    'resource_id' => $res->id,
                    'resource_name' => $res->name,
                    'resource_location' => $res->location,
                    'resource_capacity' => $res->capacity,
                    'resource_category' => $res->category,
                    'start_time' => Carbon::parse($start)->toDateTimeString(),
                    'end_time' => Carbon::parse($end)->toDateTimeString(),
                    'type' => 'alternative_resource',
                    'recent_booking_count' => $usageStats[$res->id] ?? 0,
                    'feature_similarity_score' => round($featureSimilarityScore, 1),
                    'feature_match_count' => $featureMatchCount,
                    'total_requested_features' => count($requestedFeatures),
                    'matching_features' => $this->getMatchingFeatureNames($requestedFeatures, $resFeatures),
                    'suggestion_reason' => $this->getSuggestionReason($resource, $res, $featureSimilarityScore),
                ];
            }
        }
        
        // Sort suggestions by feature similarity score (descending), then by usage (ascending)
        usort($suggestions, function($a, $b) {
            // First sort by feature similarity score (descending)
            $scoreDiff = ($b['feature_similarity_score'] ?? 0) - ($a['feature_similarity_score'] ?? 0);
            if ($scoreDiff !== 0) {
                return $scoreDiff;
            }
            // Then sort by usage (ascending - less used resources first)
            return ($a['recent_booking_count'] ?? 0) - ($b['recent_booking_count'] ?? 0);
        });
        
        return $suggestions;
    }

    /**
     * Get feature-based alternative resources suggestions.
     * This method specifically focuses on finding resources with similar features.
     */
    public function suggestFeatureBasedAlternatives($resource, $start, $end, $minFeatureMatch = 0.3)
    {
        $requestedFeatures = $resource->features()->pluck('features.id')->toArray();
        
        if (empty($requestedFeatures)) {
            return []; // No features to match against
        }
        
        $alternativeResources = Resource::where('id', '!=', $resource->id)
            ->where('is_active', true)
            ->whereHas('features', function ($query) use ($requestedFeatures) {
                $query->whereIn('features.id', $requestedFeatures);
            })
            ->with('features')
            ->get();
        
        $suggestions = [];
        
        foreach ($alternativeResources as $res) {
            $resFeatures = $res->features()->pluck('features.id')->toArray();
            $featureMatchCount = count(array_intersect($requestedFeatures, $resFeatures));
            $featureSimilarityScore = ($featureMatchCount / count($requestedFeatures)) * 100;
            
            // Only include if feature match is above minimum threshold
            if ($featureSimilarityScore >= ($minFeatureMatch * 100) && $this->isResourceAvailable($res->id, $start, $end)) {
                $suggestions[] = [
                    'resource_id' => $res->id,
                    'resource_name' => $res->name,
                    'resource_location' => $res->location,
                    'resource_capacity' => $res->capacity,
                    'resource_category' => $res->category,
                    'start_time' => Carbon::parse($start)->toDateTimeString(),
                    'end_time' => Carbon::parse($end)->toDateTimeString(),
                    'type' => 'feature_based_alternative',
                    'feature_similarity_score' => round($featureSimilarityScore, 1),
                    'feature_match_count' => $featureMatchCount,
                    'total_requested_features' => count($requestedFeatures),
                    'matching_features' => $this->getMatchingFeatureNames($requestedFeatures, $resFeatures),
                    'suggestion_reason' => "Matches {$featureMatchCount} out of " . count($requestedFeatures) . " requested features",
                ];
            }
        }
        
        // Sort by feature similarity score (descending)
        usort($suggestions, function($a, $b) {
            return ($b['feature_similarity_score'] ?? 0) - ($a['feature_similarity_score'] ?? 0);
        });
        
        return $suggestions;
    }

    /**
     * Get matching feature names for display purposes.
     */
    private function getMatchingFeatureNames($requestedFeatureIds, $resourceFeatureIds)
    {
        $matchingIds = array_intersect($requestedFeatureIds, $resourceFeatureIds);
        if (empty($matchingIds)) {
            return [];
        }
        
        return \App\Models\Feature::whereIn('id', $matchingIds)
            ->pluck('name')
            ->toArray();
    }

    /**
     * Get a human-readable reason for the suggestion.
     */
    private function getSuggestionReason($originalResource, $suggestedResource, $featureSimilarityScore)
    {
        $reasons = [];
        
        // Category match
        if ($originalResource->category === $suggestedResource->category) {
            $reasons[] = "Same category ({$originalResource->category})";
        }
        
        // Feature match
        if ($featureSimilarityScore > 0) {
            $reasons[] = "Matches " . round($featureSimilarityScore) . "% of requested features";
        }
        
        // Capacity comparison
        if ($suggestedResource->capacity >= $originalResource->capacity) {
            $reasons[] = "Same or larger capacity ({$suggestedResource->capacity})";
        }
        
        return implode(", ", $reasons);
    }

    /**
     * Allow minor overlaps if within policy.
     */
    public function allowMinorOverlap($resourceId, $start, $end, $maxOverlapMinutes = 5)
    {
        $overlapping = Booking::where('resource_id', $resourceId)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_time', [$start, $end])
                  ->orWhereBetween('end_time', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->where('start_time', '<', $start)
                         ->where('end_time', '>', $end);
                  });
            })->get();
        foreach ($overlapping as $booking) {
            $overlapStart = max(strtotime($start), strtotime($booking->start_time));
            $overlapEnd = min(strtotime($end), strtotime($booking->end_time));
            $overlapMinutes = ($overlapEnd - $overlapStart) / 60;
            if ($overlapMinutes > $maxOverlapMinutes) {
                return false;
            }
        }
        return true;
    }

    /**
     * Filter suggestions by user's existing bookings and travel time.
     */
    public function filterByUserSchedule($user, $suggestions)
    {
        $userBookings = Booking::where('user_id', $user->id)
            ->whereDate('start_time', '>=', now())
            ->get();
        $filtered = [];
        foreach ($suggestions as $suggestion) {
            $conflict = false;
            foreach ($userBookings as $booking) {
                // Check for overlap
                if (
                    ($suggestion['start_time'] < $booking->end_time) &&
                    ($suggestion['end_time'] > $booking->start_time)
                ) {
                    $conflict = true;
                    break;
                }
                // Check travel time (assume 10 min between locations for demo)
                $travelTime = 10; // minutes
                if (
                    $booking->resource_id !== $suggestion['resource_id'] &&
                    abs(strtotime($suggestion['start_time']) - strtotime($booking->end_time)) < ($travelTime * 60)
                ) {
                    $conflict = true;
                    break;
                }
            }
            if (!$conflict) {
                $filtered[] = $suggestion;
            }
        }
        return $filtered;
    }

    /**
     * Clean up old status values in the database to ensure consistency.
     * This method should be run once to fix any legacy data.
     *
     * @return int Number of records updated
     */
    public function cleanupOldStatusValues(): int
    {
        $updatedCount = 0;
        
        // Update any remaining 'in user' status to 'in_use'
        $updatedCount += Booking::where('status', 'in user')->update(['status' => self::STATUS_IN_USE]);
        
        // Update any other inconsistent status values
        $statusMappings = [
            'in user' => self::STATUS_IN_USE,
            'approved' => self::STATUS_APPROVED,
            'pending' => self::STATUS_PENDING,
            'cancelled' => self::STATUS_CANCELLED,
            'rejected' => self::STATUS_REJECTED,
            'completed' => self::STATUS_COMPLETED,
            'expired' => self::STATUS_EXPIRED,
            'preempted' => self::STATUS_PREEMPTED
        ];
        
        foreach ($statusMappings as $oldStatus => $newStatus) {
            $count = Booking::where('status', $oldStatus)->update(['status' => $newStatus]);
            $updatedCount += $count;
        }
        
        Log::info("Cleaned up {$updatedCount} booking records with old status values");
        
        return $updatedCount;
    }

    /**
     * Debug method to help troubleshoot conflict detection issues.
     *
     * @param int $resourceId
     * @param Carbon $startTime
     * @param Carbon $endTime
     * @return array
     */
    public function debugConflictDetection(int $resourceId, Carbon $startTime, Carbon $endTime): array
    {
        $resource = Resource::find($resourceId);
        if (!$resource) {
            return ['error' => 'Resource not found'];
        }

        // Get all bookings for this resource
        $allBookings = Booking::where('resource_id', $resourceId)
            ->with(['user:id,first_name,last_name,email,user_type'])
            ->get();

        // Get conflicting bookings using our method
        $conflictingBookings = $this->findConflictingBookings($resourceId, $startTime, $endTime);

        // Manual conflict check for comparison
        $manualConflicts = $allBookings->filter(function ($booking) use ($startTime, $endTime) {
            return in_array($booking->status, [
                self::STATUS_PENDING, 
                self::STATUS_APPROVED, 
                self::STATUS_IN_USE,
            ]) && $booking->start_time < $endTime && $booking->end_time > $startTime;
        });

        return [
            'resource' => [
                'id' => $resource->id,
                'name' => $resource->name,
                'capacity' => $resource->capacity,
                'is_active' => $resource->is_active
            ],
            'requested_time' => [
                'start' => $startTime->toDateTimeString(),
                'end' => $endTime->toDateTimeString()
            ],
            'all_bookings_count' => $allBookings->count(),
            'conflicting_bookings_count' => $conflictingBookings->count(),
            'manual_conflicts_count' => $manualConflicts->count(),
            'all_bookings' => $allBookings->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'status' => $booking->status,
                    'start_time' => $booking->start_time->toDateTimeString(),
                    'end_time' => $booking->end_time->toDateTimeString(),
                    'user' => $booking->user ? $booking->user->first_name . ' ' . $booking->user->last_name : 'Unknown'
                ];
            }),
            'conflicting_bookings' => $conflictingBookings->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'status' => $booking->status,
                    'start_time' => $booking->start_time->toDateTimeString(),
                    'end_time' => $booking->end_time->toDateTimeString(),
                    'user' => $booking->user ? $booking->user->first_name . ' ' . $booking->user->last_name : 'Unknown'
                ];
            })
        ];
    }

    /**
     * Main method to get all suggestions, prioritizing those that match user preferences.
     * Enhanced to include feature-based alternatives.
     */
    public function getBookingSuggestions($user, $resource, $start, $end)
    {
        $suggestions = [];
        
        // 1. Nearby slots for the same resource
        $suggestions = array_merge($suggestions, $this->suggestNearbySlots($resource->id, $start, $end));
        
        // 2. Similar resources (category and feature-based)
        $suggestions = array_merge($suggestions, $this->suggestSimilarResources($resource, $start, $end));
        
        // 3. Feature-based alternatives (specifically looking for resources with similar features)
        $featureBasedSuggestions = $this->suggestFeatureBasedAlternatives($resource, $start, $end);
        $suggestions = array_merge($suggestions, $featureBasedSuggestions);
        
        // 4. Minor overlap allowed
        if ($this->allowMinorOverlap($resource->id, $start, $end)) {
            $suggestions[] = [
                'resource_id' => $resource->id,
                'resource_name' => $resource->name,
                'resource_location' => $resource->location,
                'resource_capacity' => $resource->capacity,
                'resource_category' => $resource->category,
                'start_time' => Carbon::parse($start)->toDateTimeString(),
                'end_time' => Carbon::parse($end)->toDateTimeString(),
                'type' => 'minor_overlap_allowed',
                'suggestion_reason' => 'Minor overlap allowed for this resource'
            ];
        }
        
        // 5. Filter by user schedule
        $suggestions = $this->filterByUserSchedule($user, $suggestions);

        // 6. Advanced: Score and sort by user preferences
        $preferences = $user->preferences ?? [];
        foreach ($suggestions as &$suggestion) {
            $score = 0;
            $res = Resource::find($suggestion['resource_id']);
            if (!$res) continue;
            
            // Category match
            if (!empty($preferences['categories']) && in_array($res->category, $preferences['categories'])) {
                $score += 2;
            }
            
            // Location match
            if (!empty($preferences['locations']) && in_array($res->location, $preferences['locations'])) {
                $score += 2;
            }
            
            // Capacity match (at least as large as preferred)
            if (!empty($preferences['capacity']) && $res->capacity >= $preferences['capacity']) {
                $score += 1;
            }
            
            // Features match (count how many preferred features are present)
            if (!empty($preferences['features'])) {
                $resFeatures = $res->features()->pluck('features.name')->toArray();
                $matched = 0;
                foreach ($preferences['features'] as $feature) {
                    if (in_array($feature, $resFeatures)) {
                        $matched++;
                    }
                }
                $score += $matched;
            }
            
            // Time match (suggestion start time matches preferred times)
            if (!empty($preferences['times'])) {
                $suggestionHour = Carbon::parse($suggestion['start_time'])->format('H:i');
                foreach ($preferences['times'] as $prefTime) {
                    if (substr($suggestionHour, 0, 2) === substr($prefTime, 0, 2)) {
                        $score += 1;
                        break;
                    }
                }
            }
            
            // Add feature similarity score if available
            if (isset($suggestion['feature_similarity_score'])) {
                $score += ($suggestion['feature_similarity_score'] / 100) * 3; // Weight feature similarity
            }
            
            $suggestion['preference_score'] = $score;
        }
        unset($suggestion);
        
        // Sort suggestions by preference_score (descending), then by feature similarity
        usort($suggestions, function($a, $b) {
            // First sort by preference score
            $prefDiff = ($b['preference_score'] ?? 0) - ($a['preference_score'] ?? 0);
            if ($prefDiff !== 0) {
                return $prefDiff;
            }
            
            // Then by feature similarity score
            $featureDiff = ($b['feature_similarity_score'] ?? 0) - ($a['feature_similarity_score'] ?? 0);
            if ($featureDiff !== 0) {
                return $featureDiff;
            }
            
            // Finally by usage (less used resources first)
            return ($a['recent_booking_count'] ?? 0) - ($b['recent_booking_count'] ?? 0);
        });
        
        return $suggestions;
    }
}