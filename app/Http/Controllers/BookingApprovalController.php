<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use App\Models\Booking; 
use App\Services\BookingApprovalService; 
use App\Exceptions\BookingApprovalException; 
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Notifications\BookingApproved;

class BookingApprovalController extends Controller
{
    protected $bookingApprovalService;

    /**
     * Create a new controller instance.
     *
     * @param BookingApprovalService $bookingApprovalService
     */
    public function __construct(BookingApprovalService $bookingApprovalService)
    {
        // Inject the BookingApprovalService to handle business logic
        $this->bookingApprovalService = $bookingApprovalService;
        
        // Apply middleware to ensure only authenticated users can access these routes
        $this->middleware('auth:sanctum'); 
        
        // Middleware to check if the user is an admin
        $this->middleware(function ($request, $next) {
            if (!Auth::user() || !(Auth::user()->user_type === 'admin' || Auth::user()->role?->name === 'admin')) {
                 return response()->json(['message' => 'Unauthorized access'], 403);
            }
            return $next($request);
        });
    }

    /**
     * Get all bookings for approval (or other statuses based on filter).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        // Authorization check
        if (!$user || !($user->user_type === 'admin' || $user->role?->name === 'admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $status = $request->query('status', Booking::STATUS_PENDING); // Default to 'pending'
            $perPage = (int) $request->query('per_page', 15);
            $filters = $request->only(['resource_id', 'user_id']); // Extract other filters

            $bookings = $this->bookingApprovalService->getBookingsForApproval($status, $perPage, $filters);

            return response()->json([
                'success' => true,
                'data' => $bookings,
                'message' => 'Bookings retrieved successfully'
            ]);
        } catch (BookingApprovalException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        } catch (\Exception $e) {
            Log::error('BookingApprovalController@index failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bookings'
            ], 500);
        }
    }

    /**
     * Approve a specific booking.
     *
     * @param Request $request
     * @param Booking $booking Booking ID.
     * @return JsonResponse
     */
    public function approve(Request $request, Booking $booking): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !($user->user_type === 'admin' )) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $validatedData = $request->validate([
                'notes' => 'nullable|string|max:500'
            ]);

            $approvedBooking = $this->bookingApprovalService->approveBooking(
                $booking->id,
                $user->id,
                $validatedData['notes'] ?? null
            );

            // Send approval email to the booking owner, not the admin
            if ($approvedBooking->user) {
                $approvedBooking->user->notify(new BookingApproved($approvedBooking));
            }
            return response()->json([
                'success' => true,
                'data' => $approvedBooking,
                'message' => 'Booking approved successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (BookingApprovalException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400); // Bad Request for business logic errors, or 404 for not found
        } catch (\Exception $e) {
            Log::error('BookingApprovalController@approve failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve booking'
            ], 500);
        }
    }

    /**
     * Reject a specific booking.
     *
     * @param Request $request
     * @param Booking $booking Booking ID.
     * @return JsonResponse
     */
    public function reject(Request $request, Booking $booking): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !($user->user_type === 'admin' || $user->role?->name === 'admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $validatedData = $request->validate([
                'reason' => 'required|string|max:500',
                'notes' => 'nullable|string|max:500'
            ]);

            $rejectedBooking = $this->bookingApprovalService->rejectBooking(
                $booking->id,
                $user->id,
                $validatedData['reason'],
                $validatedData['notes'] ?? null
            );

            // Send rejection email to the booking owner, not the admin
            if ($rejectedBooking->user) {
                $rejectedBooking->user->notify(new \App\Notifications\BookingRejected($rejectedBooking, $validatedData['reason']));
            }
            return response()->json([
                'success' => true,
                'data' => $rejectedBooking,
                'message' => 'Booking rejected successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (BookingApprovalException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        } catch (\Exception $e) {
            Log::error('BookingApprovalController@reject failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject booking'
            ], 500);
        }
    }

    /**
     * Cancel a specific booking by an admin.
     *
     * @param Request $request
     * @param Booking $booking Booking ID.
     * @return JsonResponse
     */
    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !($user->user_type === 'admin' || $user->role?->name === 'admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $validatedData = $request->validate([
                'reason' => 'required|string|max:500',
                'refund_amount' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string|max:500'
            ]);

            $cancelledBooking = $this->bookingApprovalService->cancelBookingByAdmin(
                $booking->id,
                $user->id,
                $validatedData['reason'],
                $validatedData['refund_amount'] ?? null,
                $validatedData['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $cancelledBooking,
                'message' => 'Booking cancelled successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (BookingApprovalException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        } catch (\Exception $e) {
            Log::error('BookingApprovalController@cancel failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel booking'
            ], 500);
        }
    }

    /**
     * Get booking details for approval review.
     *
     * @param int $id Booking ID.
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $id = (int) $id;
        $user = Auth::user();
        if (!$user || !($user->user_type === 'admin' || $user->role?->name === 'admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $booking = $this->bookingApprovalService->getBookingDetails($id);

            return response()->json([
                'success' => true,
                'data' => $booking,
                'message' => 'Booking details retrieved successfully'
            ]);
        } catch (BookingApprovalException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 404);
        } catch (\Exception $e) {
            Log::error('BookingApprovalController@show failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve booking details'
            ], 500);
        }
    }

    /**
     * Bulk approve bookings.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !($user->user_type === 'admin' || $user->role?->name === 'admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Debug: Log the incoming request data
        Log::info('Bulk approval request data:', [
            'all_data' => $request->all(),
            'booking_ids' => $request->booking_ids,
            'notes' => $request->notes
        ]);

        try {
            $request->validate([
                'booking_uuids' => 'required|array|min:1',
                'booking_uuids.*' => 'string|exists:bookings,uuid',
                'notes' => 'nullable|string|max:500'
            ]);

            // Convert UUIDs to IDs for the service
            $bookingIds = Booking::whereIn('uuid', $request->booking_uuids)->pluck('id')->toArray();
            
            // Debug: Log the converted IDs
            Log::info('Converted booking IDs:', [
                'uuids' => $request->booking_uuids,
                'ids' => $bookingIds
            ]);

            $result = $this->bookingApprovalService->bulkApproveBookings(
                $bookingIds,
                $user->id,
                $request->notes ?? null
            );

            // Send notifications for successfully approved bookings
            if ($result['approved_count'] > 0) {
                $approvedBookings = Booking::whereIn('id', $bookingIds)
                    ->where('status', Booking::STATUS_APPROVED)
                    ->where('approved_by', $user->id)
                    ->with('user')
                    ->get();
                
                foreach ($approvedBookings as $booking) {
                    if ($booking->user) {
                        $booking->user->notify(new BookingApproved($booking));
                    }
                }
            }

            // Determine appropriate status code based on partial success or full failure
            $statusCode = 200; 
            if ($result['total_requested'] > 0 && $result['approved_count'] === 0) {
                $statusCode = 400;
            } elseif ($result['approved_count'] > 0 && $result['approved_count'] < $result['total_requested']) {
                $statusCode = 207;             }

            return response()->json([
                'success' => true, // Or false if all failed
                'data' => $result,
                'message' => $result['approved_count'] > 0
                    ? "Successfully approved {$result['approved_count']} bookings. " . (count($result['errors']) > 0 ? "Errors occurred for some: " . implode(', ', $result['errors']) : '')
                    : (count($result['errors']) > 0 ? "No bookings approved. Errors: " . implode(', ', $result['errors']) : 'No bookings were selected or are pending for approval.'),
            ], $statusCode);

        } catch (ValidationException $e) {
            // Debug: Log the validation errors
            Log::error('Bulk approval validation failed:', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (BookingApprovalException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        } catch (\Exception $e) {
            Log::error('BookingApprovalController@bulkApprove failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk approval.'
            ], 500);
        }
    }

    public function inUseApproval(Request $request, Booking $booking): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !($user->user_type === 'admin' || $user->role?->name === 'admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $validatedData = $request->validate([
                'notes' => 'nullable|string|max:500'
            ]);

            $inUseBooking = $this->bookingApprovalService->markBookingAsInUse(
                $booking->id,
                $user->id,
                $validatedData['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $inUseBooking,
                'message' => 'Booking marked as in use successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (BookingApprovalException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        } catch (\Exception $e) {
            Log::error('BookingApprovalController@inUseApproval failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark booking as in use'
            ], 500);
        }
    }   

    /**
     * Cancel a specific booking by the user.
     *
     * @param Request $request
     * @param int $id Booking ID.
     * @return JsonResponse
     */
        public function cancelBooking(Request $request, $id): JsonResponse
        {
            $id = (int) $id;
            $user = Auth::user();
            if (!$user || $user->id !== $id || !($user->user_type === 'admin' || $user->role?->name === 'admin')) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Unauthorized access, you can only cancel your own booking'
                ], 403);
            }

            try {
                $validatedData = $request->validate([
                    'reason' => 'required|string|max:500',  
                    'notes' => 'nullable|string|max:500'
                ]);

                $cancelledBooking = $this->bookingApprovalService->cancelBookingByUser(
                    $id,
                    $user->id,
                    $validatedData['reason'],
                    $validatedData['notes'] ?? null 
                );

                return response()->json([
                    'success' => true,
                    'data' => $cancelledBooking,
                    'message' => 'Booking cancelled successfully'
                ]); 
            } catch (ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            } catch (BookingApprovalException $e) { 
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], $e->getCode() ?: 400);
            } catch (\Exception $e) {
                Log::error('BookingApprovalController@cancelBooking failed: ' . $e->getMessage());
                return response()->json([
                    'success' => false, 
                    'message' => 'Failed to cancel booking'
                ], 500);
            }
        }
    
}
