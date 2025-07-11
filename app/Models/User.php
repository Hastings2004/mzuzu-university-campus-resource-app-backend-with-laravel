<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Role;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'user_type', // 'staff' or 'student'
        'email',
        'password',
        'identity_number',
        'age',
        'phone',
        'physical_address',
        'post_address',
        'district',
        'village',
        'preferences',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_backup_codes',
        'profile_visibility',
        'data_sharing',
        'email_notifications',
        'uuid',
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
            'two_factor_enabled' => 'boolean',
            'two_factor_backup_codes' => 'array',
            'data_sharing' => 'boolean',
            'email_notifications' => 'boolean',
        ];
    }

    public function bookings(): HasMany{
        return $this-> hasMany(Booking::class);
    }

    public function roles(): BelongsToMany{
        return $this-> belongsToMany(Role::class);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains('name', $roleName);
    }
    
    public function notifications() : HasMany {
        return $this->hasMany(Notification::class);
    }  

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
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

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\EmailVerificationNotification);
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}
