<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Find user by username (stored in "name" column)
        $user = User::where('name', $data['username'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
        }

        // Create custom Bearer token (returns hashed token)
        // Issue token even for unverified users so they can access verification page
        $token = \App\Models\PersonalAccessToken::createToken($user, 'api');

        // Check if email is verified
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email before logging in. Use the verification code sent to your email.',
                'token'   => $token, // Issue token so user can access verification page
                'user'    => $user,
                'requires_verification' => true,
            ], 200); // Return 200 but with requires_verification flag
        }

        return response()->json([
            'message' => 'Login successful.',
            'token'   => $token, // Hashed token (same as database)
            'user'    => $user,
            'requires_verification' => false,
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        
        if ($token) {
            // Delete token from database
            \App\Models\PersonalAccessToken::where('token', $token)->delete();
        }

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }
}
