<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Key extends Model
{
    protected $fillable = ['resource_id', 'key_code', 'status'];

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    public function transactions()
    {
        return $this->hasMany(KeyTransaction::class);
    }
} 