<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Http\Requests\StoreBookingRequest; // Make sure this Form Request exists
use App\Http\Requests\UpdateBookingRequest; // Make sure this Form Request exists
use App\Models\Resource; // Potentially still needed for some checks, but less so now
use App\Notifications\BookingCreated;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BookingController extends Controller
{
    protected $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
        // $this->middleware('auth:sanctum'); // Uncomment if you want to apply auth middleware to all methods
    }

    /**
     * Display a listing of user's bookings (or all for admin).
     * Now correctly applies filters and sorting based on user role.
     *
     * @param Request $request
     * @return JsonResponse
     */
    
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Base query - Admin sees all, regular user sees their own
        if ($user->user_type === 'admin') {
            $query = Booking::with(['resource', 'user']);
        } else {
            // Regular users see only their own bookings
            $query = $user->bookings()->with(['resource', 'user']);
            $query->whereIn('status', ['approved', 'pending']);
            
        }

        // 1. Apply Status Filter 
        $requestedStatus = $request->query('status');
        $allowedStatuses = [
            'pending',
            'approved',
            'rejected',
            'cancelled',
            'completed',
            'in_use',
            'expired',
            'preempted'
        ]; 

        if ($requestedStatus && $requestedStatus !== 'all' && in_array($requestedStatus, $allowedStatuses)) {
            $query->where('status', $requestedStatus);
        }

        // Map frontend string priority to backend integer priority_level
        $priorityMap = [
            'low' => 1,    
            'medium' => 2, 
            'high' => 3,   
        ];

        $requestedPriority = $request->query('priority'); // Frontend sends 'priority'
        if ($requestedPriority && $requestedPriority !== 'all' && isset($priorityMap[$requestedPriority])) {
            $query->where('priority', $priorityMap[$requestedPriority]);
        }

        // 2. Apply Date Range Filter 

        $sortBy = $request->query('sort_by', 'created_at'); 
        $sortOrder = $request->query('order', 'desc'); 

        // Whitelist allowed sort columns 
        $allowedSortColumns = ['start_time', 'end_time', 'created_at', 'status', 'priority'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at'; 
        }

        // Sanitize sort order
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'desc'; // Default to 'desc'

        $query->orderBy($sortBy, $sortOrder);


        // 4. Implement Pagination 
        $perPage = (int) $request->query('per_page', 15); 
        $bookings = $query->paginate($perPage);

        // Map integer priority_level to string priority for frontend response
        $priorityLevelToStringMap = array_flip($priorityMap); 

        // Prepare bookings data for consistent frontend consumption
        $formattedBookings = $bookings->getCollection()->map(function ($booking) use ($priorityLevelToStringMap) {
            return [
                'id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'user_id' => $booking->user_id,
                'resource_id' => $booking->resource_id,
                'start_time' => $booking->start_time ? $booking->start_time->toISOString() : null,
                'end_time' => $booking->end_time ? $booking->end_time->toISOString() : null,
                'status' => $booking->status,
                'purpose' => $booking->purpose,
                'booking_type' => $booking->booking_type,                
                'priority' => $priorityLevelToStringMap[$booking->priority] ?? 'unknown',
                'supporting_document' => $booking->supporting_document_path,
                'created_at' => $booking->created_at ? $booking->created_at->toISOString() : null,
                'updated_at' => $booking->updated_at ? $booking->updated_at->toISOString() : null,
                'resource' => $booking->resource ? [
                    'id' => $booking->resource->id,
                    'name' => $booking->resource->name,
                    'location' => $booking->resource->location ?? 'Unknown Location',
                    'description' => $booking->resource->description,
                    'capacity' => $booking->resource->capacity,
                    'type' => $booking->resource->type ?? null,
                    'is_active' => $booking->resource->is_active ?? null,
                ] : null,
              'user' => $booking->user ? [
                    'id' => $booking->user->id,
                    'first_name' => $booking->user->first_name ?? 'N/A',
                    'last_name' => $booking->user->last_name ?? 'N/A',
                    'email' => $booking->user->email ?? 'N/A',
                    'user_type' => $booking->user->user_type ?? $booking->user->role?->name ?? 'N/A', // Prioritize user_type, fallback to role name
                ] : null
            ];
        });

        // Log for regular user 
        if ($user->user_type !== 'admin') {
            Log::info('User retrieved their bookings.', ['user_id' => $user->id]);
        }

        // Return paginated data
        return response()->json([
            'success' => true,
            'bookings' => $formattedBookings,
            'pagination' => [
                'total' => $bookings->total(),
                'per_page' => $bookings->perPage(),
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'from' => $bookings->firstItem(),
                'to' => $bookings->lastItem(),
            ],
        ]);
    }
    /**
     * Check resource availability.
     * This method now delegates to the BookingService and includes alternative suggestions.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $request->validate([
            'resource_id' => 'required|exists:resources,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'exclude_booking_id' => 'sometimes|nullable|exists:bookings,id',
        ]);

        $resourceId = $request->input('resource_id');
        $startTime = Carbon::parse($request->input('start_time'));
        $endTime = Carbon::parse($request->input('end_time'));
        $excludeBookingId = $request->input('exclude_booking_id');

        $result = $this->bookingService->checkAvailabilityStatus(
            $resourceId,
            $startTime,
            $endTime,
            $excludeBookingId
        );

        // If resource is not available, include alternative suggestions
        if (!$result['available'] && $result['hasConflict']) {
            $user = Auth::user();
            $resource = Resource::find($resourceId);
            
            if ($user && $resource) {
                $suggestions = $this->bookingService->getBookingSuggestions(
                    $user, 
                    $resource, 
                    $startTime->toDateTimeString(), 
                    $endTime->toDateTimeString()
                );
                Log::info("suggestions");
                Log::info($suggestions);
                $result['suggestions'] = $suggestions;
                $result['message'] .= ' Here are some alternative resources that might be available:';
            }
        }

        // Map status codes for response based on the service's result
        $statusCode = $result['available'] ? 200 : ($result['hasConflict'] ? 409 : 422);

        return response()->json($result, $statusCode);
    }

    /**
     * Store a newly created booking with priority scheduling.
     * This method now delegates fully to the BookingService.
     *
     * @param StoreBookingRequest $request
     * @return JsonResponse
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $user = $request->user();
        $validatedData = $request->validated();
        
        // Ensure the supporting document file is included in the data
        if ($request->hasFile('supporting_document')) {
            $validatedData['supporting_document'] = $request->file('supporting_document');
        }
        
        Log::info('Incoming Booking Request Data:', $request->all());

        $result = $this->bookingService->createBooking($validatedData, $user);

        // Only send notification if booking was successfully created and exists
        if ($result['success'] && isset($result['booking'])) {
            $user->notify(new BookingCreated($result['booking']));
        }

        $response = [
            'success' => $result['success'],
            'message' => $result['message'],
            'booking' => $result['booking'] ?? null,
        ];
        if (isset($result['suggestions'])) {
            $response['suggestions'] = $result['suggestions'];
        }

        return response()->json($response, $result['status_code']);
    }

    /**
     * Display the specified booking.
     *
     * @param Booking $booking
     * @return JsonResponse
     */
    public function show(Booking $booking): JsonResponse
    {
        // Policy check: ensure user owns the booking or is an admin/staff
        if ($booking->user_id !== Auth::id() && !(Auth::user() && (Auth::user()->user_type === 'admin' || Auth::user()->user_type === 'staff'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'booking' => $booking->load('resource', 'user') // Load resource and user info
        ]);
    }

    /**
     * Update the specified booking.
     * This method now delegates fully to the BookingService.
     *
     * @param UpdateBookingRequest $request
     * @param Booking $booking
     * @return JsonResponse
     */
    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        // Policy check: ensure user owns the booking or is an admin
        if ($booking->user_id !== Auth::id() && !(Auth::user() && Auth::user()->user_type === 'admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = $request->user();
        $validatedData = $request->validated();
        
        // Ensure the supporting document file is included in the data
        if ($request->hasFile('supporting_document')) {
            $validatedData['supporting_document'] = $request->file('supporting_document');
        } elseif ($request->input('supporting_document') === null) {
            // If frontend sends null, it means document should be removed
            $validatedData['supporting_document'] = null;
        }

        $result = $this->bookingService->updateBooking($booking, $validatedData, $user);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'booking' => $result['booking'] ?? null,
        ], $result['status_code']);
    }

    /**
     * Cancel the specified booking.
     * This method now delegates fully to the BookingService.
     *
     * @param Booking $booking
     * @return JsonResponse
     */
    public function destroy(Booking $booking): JsonResponse
    {
        // Policy check: ensure user owns the booking or is an admin
        if ($booking->user_id !== Auth::id() && !(Auth::user() && Auth::user()->user_type === 'admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $result = $this->bookingService->cancelBooking($booking);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['status_code']);
    }

    /**
     * Cancel the specified booking via PATCH /bookings/{booking}/cancel.
     * Allows booking owner or admin to cancel.
     *
     * @param Booking $booking
     * @return JsonResponse
     */
    public function userCancelBooking(Booking $booking): JsonResponse
    {
        // Policy check: ensure user owns the booking or is an admin
        if ($booking->user_id !== Auth::id() ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $result = $this->bookingService->cancelBooking($booking);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['status_code']);
    }

    /**
     * Get upcoming bookings for the authenticated user.
     *
     * @return JsonResponse
     */
    public function getUserBookings(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Fetch upcoming bookings for the user 
        $upcomingBookings = $user->bookings()
            ->where('start_time', '>', Carbon::now())
            ->whereIn('status', ['approved', 'in_use', 'pending'])
            ->with(['resource', 'user'])
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'upcoming_bookings' => $upcomingBookings->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'booking_reference' => $booking->booking_reference,
                    'start_time' => $booking->start_time->toISOString(),
                    'end_time' => $booking->end_time->toISOString(),
                    'status' => $booking->status,
                    'supporting_document' => $booking->supporting_document_path,
                    'resource' => [
                        'id' => $booking->resource->id,
                        'name' => $booking->resource->name,
                        'location' => $booking->resource->location ?? 'Unknown Location',
                        'description' => $booking->resource->description,
                        'capacity' => $booking->resource->capacity,
                        'type' => $booking->resource->type ?? null,
                        'is_active' => $booking->resource->is_active ?? null,
                    ],
                    'user_info' => [
                        'id' => $booking->user->id ?? null,
                        'first_name' => $booking->user->first_name ?? 'N/A',
                        'last_name' => $booking->user->last_name ?? "N/A",
                        'email' => $booking->user->email ?? 'N/A',
                        'user_type' => $booking->user->user_type ?? $booking->user->role?->name ?? 'N/A',
                    ]
                ];
            })
        ]);
    }
    /**
     * Get cancellable bookings for the authenticated user.
     *
     * @return JsonResponse
     */
    
    /**
     * Serve the supporting document for a booking.
     *
     * @param Booking $booking
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
     */
    public function serveDocument(Booking $booking)
    {
        // Policy check: ensure user owns the booking or is an admin/staff
        if ($booking->user_id !== Auth::id() && !(Auth::user() && (Auth::user()->user_type === 'admin' || Auth::user()->user_type === 'staff'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        Log::info('Serving document for booking: ' . $booking->supporting_document_path);

        if (!$booking->supporting_document_path) {
            return response()->json(['message' => 'No supporting document available'], 404);
        }

        if (!Storage::disk('public')->exists($booking->supporting_document_path)) {
            return response()->json(['message' => 'Document file not found'], 404);
        }

        return Storage::disk('public')->response($booking->supporting_document_path);
    }

    /**
     * Get document metadata for a booking (for frontend viewing).
     *
     * @param Booking $booking
     * @return JsonResponse
     */
    public function getDocumentMetadata(Booking $booking)
    {
        // Policy check: ensure user owns the booking or is an admin/staff
        if ($booking->user_id !== Auth::id() && !(Auth::user() && (Auth::user()->user_type === 'admin' || Auth::user()->user_type === 'staff'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$booking->supporting_document_path) {
            return response()->json(['message' => 'No supporting document available'], 404);
        }

        if (!Storage::disk('public')->exists($booking->supporting_document_path)) {
            return response()->json(['message' => 'Document file not found'], 404);
        }

        $filename = basename($booking->supporting_document_path);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $contentType = $this->getContentType($extension);
        $fileSize = Storage::disk('public')->size($booking->supporting_document_path);

        return response()->json([
            'success' => true,
            'document' => [
                'filename' => $filename,
                'extension' => $extension,
                'content_type' => $contentType,
                'file_size' => $fileSize,
                'file_size_formatted' => $this->formatFileSize($fileSize),
                'view_url' => url("/api/bookings/{$booking->id}/document"),
                'download_url' => url("/api/bookings/{$booking->id}/download-document"),
                'can_view_in_browser' => in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'gif']),
            ]
        ]);
    }

    /**
     * Download supporting document for a booking
     */
    public function downloadDocument(Booking $booking)
    {
        try {
            // Check authorization - only admin or booking owner can download
            if (Auth::user()->user_type !== 'admin' && $booking->user_id !== Auth::id()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            if (!$booking->supporting_document_path) {
                return response()->json(['message' => 'No document found'], 404);
            }

            // Use Storage facade for portability
            $relativePath = $booking->supporting_document_path;
            if (!Storage::disk('public')->exists($relativePath)) {
                return response()->json(['message' => 'Document file not found'], 404);
            }

            $filename = basename($relativePath);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $contentType = $this->getContentType($extension);

            // Use Storage::download for proper headers
            return Storage::disk('public')->download($relativePath, $filename, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);
        } catch (\Exception $e) {
            Log::error('Document download error: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred while downloading the document'], 500);
        }
    }

    /**
     * Get content type based on file extension
     */
    private function getContentType($extension)
    {
        $contentTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        return $contentTypes[strtolower($extension)] ?? 'application/octet-stream';
    }

    /**
     * Format file size in human readable format
     */
    private function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    public function recentBookings()
    {
        $user = auth()->user();
        $recent = $user->bookings()
            ->with('resource')
            ->orderByDesc('start_time')
            ->limit(5)
            ->get();

        Log::info('Recent bookings: ' . json_encode($recent));
        return response()->json([
            'success' => true,
            'bookings' => $recent
        ]);
    }
}
