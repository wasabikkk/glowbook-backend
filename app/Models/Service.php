<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'duration_minutes',
        'is_active',
        'image',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price'     => 'decimal:2',
    ];

    protected $appends = ['image_url'];

    /**
     * Get the image URL with default fallback.
     * Returns default_service.png if no image is set or image doesn't exist.
     * Images are stored in glowbook/storage/services/
     */
    public function getImageUrlAttribute()
    {
        $baseUrl = rtrim(env('APP_URL', 'https://glowbook.ccs4thyear.com'), '/');
        
        // If image is set and exists, return it
        if ($this->image && $this->image !== 'default_service.png') {
            $imagePath = base_path('../storage/services/' . $this->image);
            if (file_exists($imagePath)) {
                return $baseUrl . '/storage/services/' . $this->image;
            }
        }
        
        // Return default service image
        $defaultPath = base_path('../storage/services/default_service.png');
        if (file_exists($defaultPath)) {
            return $baseUrl . '/storage/services/default_service.png';
        }
        
        // Fallback if default doesn't exist
        return $baseUrl . '/storage/services/default_service.png';
    }

    // Admin (user) who created this service
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Later: bookings that use this service
    // public function bookings()
    // {
    //     return $this->hasMany(Booking::class);
    // }
}
