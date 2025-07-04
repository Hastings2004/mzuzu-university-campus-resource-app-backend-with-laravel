<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Http\Requests\StoreNotificationRequest;
use App\Http\Requests\UpdateNotificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
    
        if (!$user) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized"
            ], 401); // Return 401 for unauthorized access
        }
    
        // Fetch all notifications for the authenticated user, ordered by creation date descending
        $notifications = $user->notifications()->latest()->get();
    
        return response()->json([
            "success" => true,
            "message" => "Notifications fetched successfully.",
            "notifications" => $notifications // Return the collection of notifications
        ]);
    }
    
    // New backend route/method to mark a single notification as read
    public function markAsRead(Notification $notification)
    {
        $user = Auth::user();
    
        if (!$user || $notification->user_id !== $user->id) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized or notification does not belong to user"
            ], 403); // Forbidden
        }
    
        $notification->status = 'read';
        $notification->save();
    
        return response()->json([
            "success" => true,
            "message" => "Notification marked as read successfully."
        ]);
    }
    
    // Backend route/method to mark all notifications as read (or clear all)
    public function markAllAsRead()
    {
        $user = Auth::user();
    
        if (!$user) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized"
            ], 401);
        }
    
        $user->notifications()->where('status', 'unread')->update(['status' => 'read']);
    
        return response()->json([
            "success" => true,
            "message" => "All unread notifications marked as read."
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
   public function store(StoreNotificationRequest $request): JsonResponse
    {
        $user = $request->user(); 

        $title = "Booking Confirmation";
        
        $validatedData = $request->validate([
            //'title' => 'required|string|min:1',
            'message' => 'required|string|min:1|max:255'
        ]);

        // Option B: If frontend sends nested notification object (Option 2 above)
        // $validatedData = $request->validated()['notification'];
        $title = "Booking Confirmation";

        try {
            // Create the notification linked to the user
            $notification = $user->notifications()->create(
                [
                    'title' => $title,
                    'message' => $validatedData['message'],
                    'user_id' => $user->id,
                    'status' => 'unread'
                ]
            ); // Assuming a 'notifications' relationship on User model
            // OR if it's not directly linked to a user via relationship, you might do:
            // $notification = Notification::create(array_merge($validatedData, ['user_id' => $user->id]));

            return response()->json([
                "success" => true,
                "message" => "Notification created and sent successfully.", 
                "notification" => $notification 
            ], 201); 
        } catch (\Exception $e) {
            Log::error('Error creating notification: ' . $e->getMessage(), ['user_id' => $user->id ?? 'guest', 'trace' => $e->getTraceAsString()]);
            return response()->json([
                "success" => false,
                "message" => "An unexpected error occurred while sending the notification."
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Notification $notification)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Notification $notification)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateNotificationRequest $request, Notification $notification)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Notification $notification)
    {
        //
    }


    public function unread(){
        $user = Auth::user();

        if(!$user){
            return response()->json([
                "message" => "unauthorized"
            ]);
        }

        $unread = Notification::with("user")->where("user_id", $user->id)->where("status", "unread")->count();

        return response()->json([
            "success" => true,
            "message" => "Unread notifications fetched successfully.",
            "notifications" => $unread
        ]);
    }
}