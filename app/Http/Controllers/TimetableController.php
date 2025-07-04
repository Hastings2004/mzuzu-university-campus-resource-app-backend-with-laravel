<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use App\Services\TimetableImportService;
use App\Models\Timetable;
use App\Models\Resource;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TimetableController extends Controller
{
    protected $importService;

    public function __construct(TimetableImportService $importService)
    {
        $this->importService = $importService;
        
        $this->middleware('auth:sanctum');
    }

    /**
     * Handles the import of an Excel timetable file.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls,csv|max:10240' // Max 10MB file size
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $file = $request->file('file');
        $filePath = $file->storeAs('temp_timetables_imports', 'timetable_import_' . time() . '.' . $file->extension(), 'local');
        $fullFilePath = storage_path('app/' . $filePath);

        try {
            // Clear existing timetable data before importing new one (optional, based on requirement)
            // Timetable::truncate(); 
            $this->importService->importFromExcel($fullFilePath);
            return response()->json(['success' => true, 'message' => 'Timetable imported successfully.']);
        } catch (\Exception $e) {
            Log::error("Timetable import failed: " . $e->getMessage(), ['file' => $fullFilePath]);
            return response()->json(['success' => false, 'error' => 'Import failed: ' . $e->getMessage()], 500);
        } finally {
            // Clean up the temporary file
            if (file_exists($fullFilePath)) {
                unlink($fullFilePath);
            }
        }
    }

    /**
     * Retrieves timetable entries based on provided filters.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTimetable(Request $request)
    {
        $query = Timetable::query();

        // Filter by day of week (numeric: 1-7)
        if ($request->has('day_of_week')) {
            $query->where('day_of_week', $request->day_of_week);
        }

        // Filter by room ID
        if ($request->has('room_id')) {
            $query->where('room_id', $request->room_id);
        } elseif ($request->has('room_name')) {
            // If room_name is provided, find the room_id
            $room = Resource::where('name', $request->room_name)->first();
            if ($room) {
                $query->where('room_id', $room->id);
            } else {
                return response()->json(['success' => false, 'error' => 'Room not found.'], 404);
            }
        }

        // Order by start_time for better readability
        $timetables = $query->orderBy('start_time')->get();

        // Load room names for display
        // Assuming a 'resource' relationship (belongsTo) is defined in your Timetable model
        // public function resource() { return $this->belongsTo(Resource::class); }
        $timetables->load('resource'); 

        $formattedTimetable = $timetables->map(function($item) {
            return [
                'id' => $item->id,
                'subject' => $item->subject,
                'teacher' => $item->teacher,
                'room_id' => $item->room_id,
                'room_name' => $item->resource->name ?? 'N/A', // Include room name
                'day_of_week' => $item->day_of_week,
                'start_time' => $item->start_time,
                'end_time' => $item->end_time,
                'semester' => $item->semester,
                'class_section' => $item->class_section,
                // Add any other relevant fields you want the frontend to receive
            ];
        });

        return response()->json(['success' => true, 'timetable' => $formattedTimetable]);
    }

    /**
     * Checks for time conflicts in both fixed timetables and existing bookings.
     * Assumes start_time and end_time include full date and time (YYYY-MM-DD HH:MM:SS).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkConflicts(Request $request)
    {
        try {
            $request->validate([
                'room_id' => 'required|exists:resources,id', // Ensure room exists
                'start_datetime' => 'required|date_format:Y-m-d H:i:s',
                'end_datetime' => 'required|date_format:Y-m-d H:i:s|after:start_datetime',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        $roomId = $request->input('room_id');
        $startDateTime = Carbon::parse($request->input('start_datetime'));
        $endDateTime = Carbon::parse($request->input('end_datetime'));
        $dayOfWeek = $startDateTime->dayOfWeekIso; // 1 (for Monday) through 7 (for Sunday)

        // Check timetable conflicts (fixed schedules)
        $timetableConflicts = Timetable::where('room_id', $roomId)
            ->where('day_of_week', $dayOfWeek)
            ->where(function ($query) use ($startDateTime, $endDateTime) {
                $query->whereBetween('start_time', [$startDateTime->format('H:i:s'), $endDateTime->format('H:i:s')])
                    ->orWhereBetween('end_time', [$startDateTime->format('H:i:s'), $endDateTime->format('H:i:s')])
                    ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                        $q->where('start_time', '<=', $startDateTime->format('H:i:s'))
                          ->where('end_time', '>=', $endDateTime->format('H:i:s'));
                    });
            })
            ->exists();

        // Check booking conflicts (user-made bookings)
        $bookingConflicts = Booking::where('room_id', $roomId)
            ->where('start_time', '<', $endDateTime) // Booking starts before requested end time
            ->where('end_time', '>', $startDateTime) // Booking ends after requested start time
            ->exists();
        
        return response()->json([
            'success' => true,
            'conflicts' => $timetableConflicts || $bookingConflicts,
            'details' => [
                'timetable_conflict' => $timetableConflicts,
                'booking_conflict' => $bookingConflicts
            ]
        ]);
    }

    /**
     * Creates a new booking if no conflicts are found.
     * This method assumes authentication is handled and user_id is available.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bookRoom(Request $request)
    {
        try {
            $request->validate([
                'room_id' => 'required|exists:resources,id',
                'start_datetime' => 'required|date_format:Y-m-d H:i:s',
                'end_datetime' => 'required|date_format:Y-m-d H:i:s|after:start_datetime',
                'purpose' => 'required|string|max:255',
                // 'user_id' => 'required|exists:users,id', // Uncomment if user_id is passed in request
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        $roomId = $request->input('room_id');
        $startDateTime = Carbon::parse($request->input('start_datetime'));
        $endDateTime = Carbon::parse($request->input('end_datetime'));
        $purpose = $request->input('purpose');
        $userId = auth()->id() ?? $request->input('user_id'); // Get authenticated user ID or from request

        if (!$userId) {
            return response()->json(['success' => false, 'error' => 'Authentication required or user ID missing.'], 401);
        }

        // First, check for conflicts using the dedicated method
        // We'll simulate the conflict check for direct use here,
        // as redirecting an internal request isn't ideal for API calls.
        $conflictCheckRequest = new Request([
            'room_id' => $roomId,
            'start_datetime' => $startDateTime->format('Y-m-d H:i:s'),
            'end_datetime' => $endDateTime->format('Y-m-d H:i:s'),
        ]);

        // Directly call the logic from checkConflicts to get conflict status
        $timetableConflicts = Timetable::where('room_id', $roomId)
            ->where('day_of_week', $startDateTime->dayOfWeekIso)
            ->where(function ($query) use ($startDateTime, $endDateTime) {
                $query->whereBetween('start_time', [$startDateTime->format('H:i:s'), $endDateTime->format('H:i:s')])
                    ->orWhereBetween('end_time', [$startDateTime->format('H:i:s'), $endDateTime->format('H:i:s')])
                    ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                        $q->where('start_time', '<=', $startDateTime->format('H:i:s'))
                          ->where('end_time', '>=', $endDateTime->format('H:i:s'));
                    });
            })
            ->exists();

        $bookingConflicts = Booking::where('room_id', $roomId)
            ->where('start_time', '<', $endDateTime)
            ->where('end_time', '>', $startDateTime)
            ->exists();
        
        if ($timetableConflicts || $bookingConflicts) {
            return response()->json([
                'success' => false,
                'error' => 'The selected time slot has conflicts.',
                'details' => [
                    'timetable_conflict' => $timetableConflicts,
                    'booking_conflict' => $bookingConflicts
                ]
            ], 409); // 409 Conflict
        }

        // No conflicts, proceed with booking
        try {
            $booking = Booking::create([
                'user_id' => $userId,
                'room_id' => $roomId,
                'start_time' => $startDateTime,
                'end_time' => $endDateTime,
                'purpose' => $purpose,
                'status' => 'confirmed', // Or 'pending' if approval is needed
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Room booked successfully!',
                'booking' => $booking
            ], 201); // 201 Created
        } catch (\Exception $e) {
            Log::error("Failed to create booking: " . $e->getMessage(), $request->all());
            return response()->json(['success' => false, 'error' => 'Failed to create booking: ' . $e->getMessage()], 500);
        }
    }
}