<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timetable extends Model
{
    protected $fillable = [
        'course_code', 
        'subject',
        'teacher',
        'room_id',
        'day_of_week', 
        'start_time', 
        'end_time', 
        'semester', 
        'class_section',
        'course_name',
        'room',
        'date',
        'type'
    ];

    /**
     * Get the resource (room) that this timetable entry is for.
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'room_id');
    }
}
