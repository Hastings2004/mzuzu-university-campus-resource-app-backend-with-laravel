<?php

namespace App\Http\Controllers;

use App\Models\Key;
use App\Models\KeyTransaction;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class KeyTransactionController extends Controller
{
    // POST /api/keys/{key}/checkout
    public function checkout(Request $request, Key $key)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|exists:bookings,id',
            'user_id' => 'required|exists:users,id',
            'custodian_id' => 'required|exists:users,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($key->status !== 'available') {
            return response()->json(['error' => 'Key is not available for check-out.'], 400);
        }

        $booking = Booking::findOrFail($request->booking_id);
        $expectedReturn = $booking->end_time ?? now()->addHours(1); // fallback if no end_time

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
    }

    // POST /api/keys/{key}/checkin
    public function checkin(Request $request, Key $key)
    {
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
    }

    // GET /api/keys/{key}/transactions
    public function transactions(Key $key)
    {
        $transactions = $key->transactions()->with(['user', 'custodian', 'booking'])->orderByDesc('checked_out_at')->get();
        return response()->json($transactions);
    }

    // GET /api/key-transactions/overdue
    public function overdue()
    {
        $now = now();
        $overdue = KeyTransaction::where('status', 'checked_out')
            ->where('expected_return_at', '<', $now)
            ->with(['key', 'user', 'custodian', 'booking'])
            ->orderBy('expected_return_at')
            ->get();
        return response()->json($overdue);
    }
} 