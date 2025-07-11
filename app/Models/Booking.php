<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon; 
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'booking_reference',
        'user_id',
        'resource_id',
        'start_time',
        'end_time',
        'status',
        'purpose',
        'booking_type',     
        'priority',   
        'cancelled_at',
        'cancellation_reason',
        'supporting_document_path',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        'approved_by',
        'approved_at',
        'admin_notes',
        'cancelled_by',
        'refund_amount',
        'in_use_started_by',
        'in_use_started_at',
        'completed_by',
        'completed_at',
        'will_complete_notified_at',
        'completed_notified_at',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'cancelled_at' => 'datetime',
        'rejected_at' => 'datetime',
        'approved_at' => 'datetime',
        'refund_amount' => 'decimal:2',
    ];

    // Define booking statuses as constants for better readability and maintainability
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_PREEMPTED = 'preempted';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_IN_USE = 'in_use'; 
    public const STATUS_EXPIRED = 'expired'; 

    /**
     * Get the user that owns the booking.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the resource that the booking is for.
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     * Get the admin who approved the booking.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the admin who rejected the booking.
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get the admin who cancelled the booking.
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Check if the booking has expired (start time is in the past)
     */
    public function isExpired(): bool
    {
        return $this->start_time->isPast();
    }

    /**
     * Check if the booking can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return !$this->isExpired() &&
               $this->status !== self::STATUS_CANCELLED &&
               $this->status !== self::STATUS_PREEMPTED; // Cannot cancel if already preempted
    }

    // Scopes for easier querying (optional, but good practice)
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    public function scopeNotExpired($query)
    {
        return $query->where('end_time', '>', Carbon::now());
    }
    public function scopeNotStarted($query)
    {
        return $query->where('start_time', '>', Carbon::now());
    }
    public function scopeCancellable($query)
    {
        return $query->where('status', self::STATUS_APPROVED)
                     ->orWhere('status', self::STATUS_PENDING)
                     ->where('start_time', '>', Carbon::now());
    }
    /**
     * Get the payment associated with the booking.
     */
    // This assumes a one-to-one relationship with Payment   

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Use uuid for route model binding
     */
    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
