<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PersonalAccessToken extends Model
{
    protected $table = 'personal_access_tokens';

    protected $fillable = [
        'tokenable_type',
        'tokenable_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'abilities' => 'array',
    ];

    /**
     * Generate a new token hash and store it.
     * Returns the hash (same as what goes in localStorage).
     */
    public static function createToken($tokenable, string $name = 'api', array $abilities = ['*'], $expiresAt = null): string
    {
        // Generate a random token string
        $randomToken = Str::random(80);
        
        // Hash it with SHA256 (64 characters)
        $tokenHash = hash('sha256', $randomToken);
        
        // Store the hash in database
        $token = static::create([
            'tokenable_type' => get_class($tokenable),
            'tokenable_id' => $tokenable->id,
            'name' => $name,
            'token' => $tokenHash,
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);
        
        // Return the hash (this goes to localStorage)
        return $tokenHash;
    }

    /**
     * Find token by hash.
     */
    public static function findToken(string $tokenHash)
    {
        return static::where('token', $tokenHash)
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    /**
     * Get the tokenable model (User).
     */
    public function tokenable()
    {
        return $this->morphTo();
    }

    /**
     * Delete a token by its hash.
     * Returns true if token was found and deleted, false otherwise.
     */
    public static function deleteByToken(string $tokenHash): bool
    {
        $deleted = static::where('token', $tokenHash)->delete();
        return $deleted > 0;
    }

    /**
     * Delete all tokens for a specific user.
     */
    public static function deleteAllForUser($user): int
    {
        return static::where('tokenable_type', get_class($user))
            ->where('tokenable_id', $user->id)
            ->delete();
    }
}
