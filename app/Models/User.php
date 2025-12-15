<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'role',             // admin | aesthetician | client
        'is_super_admin',   // boolean
        'phone',
        'avatar',
        'email_verified_at', // Allow mass assignment for email verification
        'address',          // Allow mass assignment for address
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_super_admin'    => 'boolean',
    ];

    /**
     * Get the avatar URL with default fallback.
     * Avatars are stored in glowbook/storage/avatars/
     */
    public function getAvatarUrlAttribute()
    {
        $baseUrl = rtrim(env('APP_URL', 'https://glowbook.ccs4thyear.com'), '/');
        
        // If avatar is set and exists, return it
        if ($this->avatar && $this->avatar !== 'default.png') {
            $avatarPath = base_path('../storage/avatars/' . $this->avatar);
            if (file_exists($avatarPath)) {
                return $baseUrl . '/storage/avatars/' . $this->avatar;
            }
        }
        
        // Return default avatar
        $defaultPath = base_path('../storage/avatars/default.png');
        if (file_exists($defaultPath)) {
            return $baseUrl . '/storage/avatars/default.png';
        }
        
        // Fallback
        return $baseUrl . '/storage/avatars/default.png';
    }

    /* -----------------------------------
     | ROLE HELPERS
     |-----------------------------------*/

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'admin' && $this->is_super_admin === true;
    }

    public function isAesthetician(): bool
    {
        return $this->role === 'aesthetician';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    /* -----------------------------------
     | RELATIONSHIPS
     |-----------------------------------*/

    // Bookings made BY this user (client)
    public function clientBookings()
    {
        return $this->hasMany(Booking::class, 'user_id');
    }

    // Bookings handled BY this user (aesthetician)
    public function aestheticianBookings()
    {
        return $this->hasMany(Booking::class, 'aesthetician_id');
    }

    // Email verification codes
    public function emailVerificationCodes()
    {
        return $this->hasMany(EmailVerificationCode::class);
    }
}
