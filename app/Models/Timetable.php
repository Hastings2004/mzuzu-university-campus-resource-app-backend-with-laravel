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
        'type',
        'study_mode',
        'delivery_mode',
        'program_type'
    ];

    /**
     * Get the resource (room) that this timetable entry is for.
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'room_id');
    }

    /**
     * Scope for full-time students
     */
    public function scopeFullTime($query)
    {
        return $query->where('study_mode', 'full-time');
    }

    /**
     * Scope for part-time students
     */
    public function scopePartTime($query)
    {
        return $query->where('study_mode', 'part-time');
    }

    /**
     * Scope for face-to-face classes
     */
    public function scopeFaceToFace($query)
    {
        return $query->where('delivery_mode', 'face-to-face');
    }

    /**
     * Scope for online classes
     */
    public function scopeOnline($query)
    {
        return $query->where('delivery_mode', 'online');
    }

    /**
     * Scope for hybrid classes
     */
    public function scopeHybrid($query)
    {
        return $query->where('delivery_mode', 'hybrid');
    }
}
