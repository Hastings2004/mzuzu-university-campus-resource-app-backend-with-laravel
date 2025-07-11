<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Resource extends Model
{
    /** @use HasFactory<\Database\Factories\ResourceFactory> */
    use HasFactory;

    
    protected $fillable = [
        'name',
        'description',
        'location',
        'capacity',
        'category',
        'status',
        'image',
        'special_approval'
    ];

    protected $casts = [
        'capacity' => 'integer',
        'status' => 'string'
    ];

    protected $appends = ['image_url'];

    /**
     * Get the bookings associated with the resource.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get the full image URL for the resource.
     */
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return \Storage::disk('public')->url($this->image);
        }
        return null;
    }

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'feature_resource');
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
