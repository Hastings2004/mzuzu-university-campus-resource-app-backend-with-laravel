<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timetable extends Model
{
    protected $fillable = [
        'course_code', 'room', 'day_of_week', 
        'start_time', 'end_time', 'semester', 'class_section'
    ];
}
