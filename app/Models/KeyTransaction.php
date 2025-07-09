<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeyTransaction extends Model
{
    protected $fillable = [
        'key_id', 'booking_id', 'user_id', 'custodian_id',
        'checked_out_at', 'expected_return_at', 'checked_in_at', 'status',
        'overdue_notified_at'
    ];

    protected $casts = [
        'checked_out_at' => 'datetime',
        'expected_return_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'overdue_notified_at' => 'datetime',
    ];

    public function key()
    {
        return $this->belongsTo(Key::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function custodian()
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }
} 