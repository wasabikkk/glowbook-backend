<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    // Status constants (for cleaner comparisons)
    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED   = 'expired';

    protected $fillable = [
        'user_id',
        'aesthetician_id',
        'service_id',
        'appointment_date',
        'appointment_time',
        'status',
        'client_note',
        'aesthetician_note',
    ];

    protected $casts = [
        'appointment_date' => 'date',
    ];

    // Relationships
    public function client()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function aesthetician()
    {
        return $this->belongsTo(User::class, 'aesthetician_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
