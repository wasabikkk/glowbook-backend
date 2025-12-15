<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\VerifyEmailCodeController;
use App\Http\Controllers\Auth\ResendVerificationCodeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\BookingController;

/*
|--------------------------------------------------------------------------
| Public Auth Routes (no token yet)
|--------------------------------------------------------------------------
*/
Route::post('/register',        [RegisteredUserController::class, 'store']);
Route::post('/login',           [AuthenticatedSessionController::class, 'store']);
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
Route::post('/reset-password',  [NewPasswordController::class, 'store']);

/*
|--------------------------------------------------------------------------
| Protected Routes (need Bearer token)
|--------------------------------------------------------------------------
*/
Route::middleware([\App\Http\Middleware\AuthenticateBearerToken::class])->group(function () {

    // ---- Logout ----
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    // ---- Email verification with code ----
    Route::post('/verify-email-code', [VerifyEmailCodeController::class, 'store']);
    Route::post('/resend-verification-code', [ResendVerificationCodeController::class, 'store'])
        ->middleware(['throttle:6,1']);

    /*
    |--------------------------------------------------------------------------
    | Routes that require verified email
    |--------------------------------------------------------------------------
    */
    Route::middleware('verified')->group(function () {

        /*
        |--------------------------------------------------------------
        | Common: Profile Management
        |--------------------------------------------------------------
        */
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::match(['put', 'post'], '/profile', [ProfileController::class, 'update']);
        Route::post('/profile/change-password', [ProfileController::class, 'changePassword']);

        /*
        |--------------------------------------------------------------
        | Admin: User & Service Management
        | prefix: /api/admin/...
        |--------------------------------------------------------------
        */
        Route::middleware('role:admin')->prefix('admin')->group(function () {

            // USER MANAGEMENT
            Route::get   ('/users',          [UserController::class, 'index']);
            Route::post  ('/users',          [UserController::class, 'store']);
            Route::get   ('/users/{user}',   [UserController::class, 'show']);
            Route::put   ('/users/{user}',   [UserController::class, 'update']);
            Route::delete('/users/{user}',   [UserController::class, 'destroy']);

            // SERVICE MANAGEMENT
            Route::get   ('/services',                 [ServiceController::class, 'index']);
            Route::post  ('/services',                 [ServiceController::class, 'store']);
            // Use POST with _method=PUT for FormData compatibility, Laravel will handle method spoofing
            Route::match(['put', 'post'], '/services/{service}', [ServiceController::class, 'update']);
            Route::delete('/services/{service}',       [ServiceController::class, 'destroy']);
        });

        /*
        |--------------------------------------------------------------
        | ALL ROLES: Services (public-facing)
        |--------------------------------------------------------------
        */
        Route::get('/services',           [ServiceController::class, 'publicIndex']);
        Route::get('/services/{service}', [ServiceController::class, 'show']);

        /*
        |--------------------------------------------------------------
        | ALL ROLES: Get Aestheticians (for booking)
        |--------------------------------------------------------------
        */
        Route::get('/aestheticians', function () {
            $aestheticians = \App\Models\User::where('role', 'aesthetician')
                ->select('id', 'first_name', 'last_name', 'email')
                ->get();
            return response()->json(['items' => $aestheticians]);
        });

        /*
        |--------------------------------------------------------------
        | ALL ROLES: Bookings (Step 5)
        |--------------------------------------------------------------
        | - index(): response depends on role
        | - store(): client creates booking
        | - cancel(): client cancels PENDING booking
        | - updateStatus(): admin / aesthetician change status
        | - destroy(): admin hard-deletes booking
        */
        Route::get   ('/bookings',                       [BookingController::class, 'index']);
        Route::post  ('/bookings',                       [BookingController::class, 'store']);
        Route::post  ('/bookings/{booking}/cancel',      [BookingController::class, 'cancel']);
        Route::put   ('/bookings/{booking}/status',      [BookingController::class, 'updateStatus']);
        Route::delete('/bookings/{booking}',             [BookingController::class, 'destroy']);
    });
});
