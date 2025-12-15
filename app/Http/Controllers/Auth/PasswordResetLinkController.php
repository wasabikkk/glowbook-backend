<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PasswordResetLinkController extends Controller
{
    /**
     * Handle an incoming password reset code request.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        // Don't reveal if email exists or not for security
        if ($user) {
            // Generate reset code
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            PasswordResetCode::create([
                'email' => $user->email,
                'code' => $code,
                'expires_at' => now()->addMinutes(15),
            ]);

            Mail::to($user->email)->send(new PasswordResetCodeMail($code));
        }

        return response()->json([
            'message' => 'If the email exists, a password reset code has been sent.',
        ]);
    }
}
