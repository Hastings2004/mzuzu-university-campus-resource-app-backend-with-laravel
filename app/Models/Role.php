<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    //
    protected $fillable = ['name', 'display_name'];

    /**
     * The users that belong to the Role
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
