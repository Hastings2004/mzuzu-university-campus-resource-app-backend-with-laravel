<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ResourceIssue extends Model
{
    //
    use HasFactory;

    protected $fillable = [ 
        'reported_by_user_id',
        'resource_id',
        'subject',
        'description',
        'photo_path',
        'status',
        'resolved_at',
        'resolved_by_user_id',
        'issue_type',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    protected $appends = ['photo_url'];

    /**
     * Get the full URL for the photo.
     */
    public function getPhotoUrlAttribute()
    {
        if ($this->photo_path) {
            return Storage::disk('public')->url($this->photo_path);
        }
        return null;
    }

    /**
     * Get the resource that the issue is about.
     */
    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     * Get the user who reported the issue.
     */
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /**
     * Get the user who resolved the issue.
     */
    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
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
}
