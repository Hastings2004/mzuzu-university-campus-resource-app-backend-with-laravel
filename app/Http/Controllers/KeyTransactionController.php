<?php

namespace App\Http\Controllers;

use App\Models\Key;
use App\Models\KeyTransaction;
use App\Models\Booking;
use App\Models\User;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class KeyTransactionController extends Controller
{

    //store keys
    public function store(Request $request){
        try {
            $user = Auth::user();

            if($user->user_type != 'admin'){
                return response()->json([
                    'success'=> false,
                    'message'=> "This action is unauthorized"
                ], 403);
            }
            
            $validator = Validator::make($request->all(),[
                'name' => 'required|exists:resources,name',
                'key_code' => 'required|string|unique:keys,key_code',
                'status' => 'required|in:available,checked_out,lost'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $resource = Resource::where('name', $request->name)->firstOrFail();

            $keys = Key::create([
                'resource_id'=>$resource->id,
                'key_code'=>$request->key_code,
                'status'=>$request->status
            ]);

            return response()->json([
                'success'=> true,
                'message'=> "Key successfully added",
                'key' => $keys
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Key store error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the key. Please try again.'
            ], 500);
        }
    }
    
    // POST /api/keys/{key}/checkout
    public function checkout(Request $request, Key $key)
    {
        try {
            $validator = Validator::make($request->all(), [
                'booking_reference' => 'required|exists:bookings',
                'user_id' => 'required|exists:users,id',
                'custodian_id' => 'required|exists:users,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            if ($key->status !== 'available') {
                return response()->json(['error' => 'Key is not available for check-out.'], 400);
            }

            // Use booking_reference instead of booking_id for lookup, as per validation
            $booking = Booking::where('booking_reference', $request->booking_reference)->firstOrFail();
            $expectedReturn = $booking->end_time; // always use booking end_time

            $transaction = KeyTransaction::create([
                'key_id' => $key->id,
                'booking_id' => $booking->id,
                'user_id' => $request->user_id,
                'custodian_id' => $request->custodian_id,
                'checked_out_at' => now(),
                'expected_return_at' => $expectedReturn,
                'status' => 'checked_out',
            ]);

            $key->status = 'checked_out';
            $key->save();

            return response()->json(['message' => 'Key checked out successfully.', 'transaction' => $transaction]);
            
        } catch (\Exception $e) {
            Log::error('Key checkout error: ' . $e->getMessage(), [
                'key_id' => $key->id,
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'error' => 'An error occurred while checking out the key. Please try again.'
            ], 500);
        }
    }

    // POST /api/keys/{key}/checkin
    public function checkin(Request $request, Key $key)
    {
        try {
            $validator = Validator::make($request->all(), [
                'custodian_id' => 'required|exists:users,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $transaction = KeyTransaction::where('key_id', $key->id)
                ->where('status', 'checked_out')
                ->latest('checked_out_at')
                ->first();

            if (!$transaction) {
                return response()->json(['error' => 'No active check-out found for this key.'], 404);
            }

            $transaction->checked_in_at = now();
            $transaction->custodian_id = $request->custodian_id;
            $transaction->status = 'returned';
            $transaction->save();

            $key->status = 'available';
            $key->save();

            return response()->json(['message' => 'Key checked in successfully.', 'transaction' => $transaction]);
            
        } catch (\Exception $e) {
            Log::error('Key checkin error: ' . $e->getMessage(), [
                'key_id' => $key->id,
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'error' => 'An error occurred while checking in the key. Please try again.'
            ], 500);
        }
    }

    // GET /api/keys/{key}/transactions
    public function transactions(Key $key)
    {
        try {
            $transactions = $key->transactions()->with(['user', 'custodian', 'booking'])->orderByDesc('checked_out_at')->paginate(10); // Paginate transactions
            return response()->json($transactions);
            
        } catch (\Exception $e) {
            Log::error('Key transactions error: ' . $e->getMessage(), [
                'key_id' => $key->id
            ]);
            
            return response()->json([
                'error' => 'An error occurred while fetching transactions. Please try again.'
            ], 500);
        }
    }

    // GET /api/key-transactions/overdue
    public function overdue()
    {
        try {
            $now = now();
            $overdue = KeyTransaction::where('status', 'checked_out')
                ->where('expected_return_at', '<', $now)
                ->with(['key', 'user', 'custodian', 'booking'])
                ->orderBy('expected_return_at')
                ->paginate(10); // Paginate overdue
            return response()->json($overdue);
            
        } catch (\Exception $e) {
            Log::error('Overdue transactions error: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'An error occurred while fetching overdue transactions. Please try again.'
            ], 500);
        }
    }

    // GET /api/keys
    public function index()
    {
        try {
            $keys = Key::with('resource')->paginate(10); // Paginate keys
            return response()->json($keys);
        } catch (\Exception $e) {
            \Log::error('Error fetching keys: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch keys.'], 500);
        }
    }
} 