<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBearerToken
{
    /**
     * Handle an incoming request - authenticate using Bearer token.
     * Detects tampered tokens and automatically deletes them.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'message' => 'Unauthenticated. Missing Bearer token.',
            ], 401);
        }

        // Validate token format (should be 64 hex characters for SHA256)
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            // Token format is invalid (tampered) - delete exact match if exists
            $deleted = PersonalAccessToken::deleteByToken($token);
            
            // Log the invalid format attempt
            \Log::warning('Invalid token format detected', [
                'token_length' => strlen($token),
                'ip' => $request->ip(),
                'deleted_count' => $deleted,
            ]);
            
            return response()->json([
                'message' => 'Unauthenticated. Invalid token format.',
            ], 401);
        }

        // Find token in database (token is already hashed)
        $tokenRecord = PersonalAccessToken::findToken($token);
        
        if (!$tokenRecord) {
            // Token is invalid/tampered/expired - try to delete exact match first
            $deleted = PersonalAccessToken::deleteByToken($token);
            
            // Since the token hash doesn't match, it was likely tampered
            // We need to find and delete the original token
            // Strategy: Delete ALL tokens that were used OR created within the last 24 hours
            // This aggressive approach ensures we catch the tampered token regardless of timing
            
            // First, try to delete tokens used in the last 24 hours
            $deletedUsed = PersonalAccessToken::where('last_used_at', '>=', now()->subHours(24))->delete();
            
            // Then, delete tokens created in the last 24 hours (even if never used)
            $deletedCreated = PersonalAccessToken::where('created_at', '>=', now()->subHours(24))->delete();
            
            // Also delete tokens that have never been used but were created in the last 7 days
            $deletedUnused = PersonalAccessToken::whereNull('last_used_at')
                ->where('created_at', '>=', now()->subDays(7))
                ->delete();
            
            $totalDeleted = $deletedUsed + $deletedCreated + $deletedUnused;
            
            \Log::warning('Tampered or invalid token detected - deleting tokens', [
                'token_prefix' => substr($token, 0, 16) . '...', // Log first 16 chars only for security
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'exact_match_deleted' => $deleted,
                'used_tokens_deleted' => $deletedUsed,
                'created_tokens_deleted' => $deletedCreated,
                'unused_tokens_deleted' => $deletedUnused,
                'total_deleted' => $totalDeleted,
                'time_window' => '24 hours used/created or 7 days unused',
                'note' => 'Deleted tokens to remove the tampered token',
            ]);
            
            return response()->json([
                'message' => 'Unauthenticated. Invalid, expired, or tampered token. Please login again.',
            ], 401);
        }

        // Check if token has expired
        if ($tokenRecord->expires_at && $tokenRecord->expires_at->isPast()) {
            // Token expired - delete it
            $tokenRecord->delete();
            
            return response()->json([
                'message' => 'Unauthenticated. Token has expired. Please login again.',
            ], 401);
        }

        // Update last used timestamp
        $tokenRecord->update(['last_used_at' => now()]);

        // Set authenticated user
        $user = $tokenRecord->tokenable;
        if ($user) {
            auth()->setUser($user);
        }

        return $next($request);
    }
}
