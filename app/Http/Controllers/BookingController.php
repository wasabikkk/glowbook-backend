<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * Auto-expire pending bookings where the date has already passed.
     */
    protected function expireOldPending(): void
    {
        $today = Carbon::today()->toDateString();

        Booking::where('status', Booking::STATUS_PENDING)
            ->where('appointment_date', '<', $today)
            ->update(['status' => Booking::STATUS_EXPIRED]);
    }

    /**
     * List bookings depending on user role.
     * - Admin: all bookings + optional filters
     * - Aesthetician: only bookings assigned to them
     * - Client: only their own bookings
     */
    public function index(Request $request)
    {
        $this->expireOldPending();

        $user = auth()->user();

        if ($user->isAdmin()) {
            $q = Booking::with(['client', 'aesthetician', 'service']);

            if ($status = $request->query('status')) {
                $q->where('status', $status);
            }

            if ($request->has('date_from')) {
                $q->where('appointment_date', '>=', $request->query('date_from'));
            }

            if ($request->has('date_to')) {
                $q->where('appointment_date', '<=', $request->query('date_to'));
            }

            if ($request->has('client_id')) {
                $q->where('user_id', $request->query('client_id'));
            }

            if ($request->has('aesthetician_id')) {
                $q->where('aesthetician_id', $request->query('aesthetician_id'));
            }

            $bookings = $q->orderBy('appointment_date')
                          ->orderBy('appointment_time')
                          ->get();
        } elseif ($user->isAesthetician()) {
            $bookings = Booking::with(['client', 'service'])
                ->where('aesthetician_id', $user->id)
                ->orderBy('appointment_date')
                ->orderBy('appointment_time')
                ->get();
        } else {
            // client
            // If aesthetician_id is provided, return bookings for that aesthetician (for availability checking)
            // Only return approved/completed bookings to check availability
            if ($request->has('aesthetician_id') && $request->has('date_from') && $request->has('date_to')) {
                // This is an availability check - return bookings for the aesthetician on the specified date
                $q = Booking::with(['aesthetician', 'service'])
                    ->where('aesthetician_id', $request->query('aesthetician_id'))
                    ->where('appointment_date', '>=', $request->query('date_from'))
                    ->where('appointment_date', '<=', $request->query('date_to'))
                    ->whereIn('status', ['approved', 'completed']); // Only check approved/completed bookings
                
                $bookings = $q->orderBy('appointment_date')
                    ->orderBy('appointment_time')
                    ->get();
            } else {
                // Normal query - return user's own bookings
                $bookings = Booking::with(['aesthetician', 'service'])
                    ->where('user_id', $user->id)
                    ->orderBy('appointment_date')
                    ->orderBy('appointment_time')
                    ->get();
            }
        }

        return response()->json(['items' => $bookings]);
    }

    /**
     * Client creates a booking.
     * - No same-day booking (must be after today)
     * - No double booking for same user/date/time
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user->isClient()) {
            return response()->json(['error' => 'Only clients can create bookings.'], 403);
        }

        $data = $request->validate([
            'service_id'       => ['required', Rule::exists('services', 'id')],
            'aesthetician_id'  => [
                'required', // Changed to required to match your database
                Rule::exists('users', 'id')->where('role', 'aesthetician'),
            ],
            'appointment_date' => ['required', 'date', 'after:today'], // No same-day booking
            'appointment_time' => ['required', 'date_format:H:i'],
            'client_note'      => ['nullable', 'string'],
        ]);
        
        // Additional validation: Ensure date is not today (strict check)
        $today = \Carbon\Carbon::today()->toDateString();
        if ($data['appointment_date'] <= $today) {
            return response()->json([
                'error' => 'Booking must be for tomorrow or later. Same-day bookings are not allowed.',
            ], 422);
        }

        // No double booking for the same client
        $exists = Booking::where('user_id', $user->id)
            ->where('appointment_date', $data['appointment_date'])
            ->where('appointment_time', $data['appointment_time'])
            ->whereNotIn('status', [
                Booking::STATUS_CANCELLED,
                Booking::STATUS_REJECTED,
                Booking::STATUS_EXPIRED,
            ])
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'You already have a booking at this date & time.',
            ], 422);
        }

        // Check if the aesthetician already has an approved or completed booking at this date & time
        $aestheticianBooked = Booking::where('aesthetician_id', $data['aesthetician_id'])
            ->where('appointment_date', $data['appointment_date'])
            ->where('appointment_time', $data['appointment_time'])
            ->whereIn('status', [
                Booking::STATUS_APPROVED,
                Booking::STATUS_COMPLETED,
            ])
            ->exists();

        if ($aestheticianBooked) {
            return response()->json([
                'error' => 'This aesthetician already has an approved or completed appointment at this date & time. Please select a different time slot or aesthetician.',
            ], 422);
        }

        $booking = Booking::create([
            'user_id'         => $user->id,
            'aesthetician_id' => $data['aesthetician_id'],
            'service_id'      => $data['service_id'],
            'appointment_date'=> $data['appointment_date'],
            'appointment_time'=> $data['appointment_time'],
            'status'          => Booking::STATUS_PENDING,
            'client_note'     => $data['client_note'] ?? null,
        ]);

        return response()->json(['booking' => $booking], 201);
    }

    /**
     * Client cancels their own pending booking.
     */
    public function cancel(Request $request, Booking $booking)
    {
        $user = auth()->user();

        if (!$user->isClient() || $booking->user_id !== $user->id) {
            return response()->json(['error' => 'You cannot cancel this booking.'], 403);
        }

        if ($booking->status !== Booking::STATUS_PENDING) {
            return response()->json(['error' => 'Only pending bookings can be cancelled.'], 422);
        }

        $booking->status = Booking::STATUS_CANCELLED;
        $booking->save();

        return response()->json(['booking' => $booking]);
    }

    /**
     * Admin / Aesthetician updates booking status with rules:
     *
     * Flow rules:
     *  - Pending → (client cancels) → Cancelled (handled in cancel())
     *  - Pending → (aesthetician approves) → Approved
     *  - Pending → (aesthetician rejects) → Rejected
     *  - Pending → (date passes) → Expired (auto)
     *  - Approved → Completed
     *
     * Restrictions:
     *  - Approved → Rejected : ❌
     *  - Rejected → Approved : ❌
     *  - Completed / Expired / Cancelled → any change : ❌
     *  - Pending → Completed : ❌ (must be approved first)
     */
    public function updateStatus(Request $request, Booking $booking)
    {
        $user = auth()->user();

        if (!$user->isAdmin() && !$user->isAesthetician()) {
            return response()->json(['error' => 'Only admin or aesthetician can change status.'], 403);
        }

        // Aesthetician can only manage own bookings
        if ($user->isAesthetician() && $booking->aesthetician_id !== $user->id) {
            return response()->json(['error' => 'You can only manage your own bookings.'], 403);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in([
                Booking::STATUS_PENDING,
                Booking::STATUS_APPROVED,
                Booking::STATUS_REJECTED,
                Booking::STATUS_CANCELLED,
                Booking::STATUS_COMPLETED,
                Booking::STATUS_EXPIRED,
            ])],
            'aesthetician_note' => ['nullable', 'string'],
        ]);

        $newStatus = $data['status'];
        $current   = $booking->status;

        // Final states cannot be changed
        if (in_array($current, [
            Booking::STATUS_COMPLETED,
            Booking::STATUS_EXPIRED,
            Booking::STATUS_CANCELLED,
        ], true)) {
            return response()->json(['error' => 'This booking can no longer be changed.'], 422);
        }

        // Pending rules
        if ($current === Booking::STATUS_PENDING) {
            if ($newStatus === Booking::STATUS_COMPLETED) {
                return response()->json([
                    'error' => 'Pending bookings must be approved before completion.',
                ], 422);
            }

            if ($newStatus === Booking::STATUS_EXPIRED) {
                return response()->json([
                    'error' => 'Expired is set automatically when the date passes.',
                ], 422);
            }
        }

        // Approved rules
        if ($current === Booking::STATUS_APPROVED) {
            if (in_array($newStatus, [
                Booking::STATUS_APPROVED,
                Booking::STATUS_REJECTED,
                Booking::STATUS_PENDING,
            ], true)) {
                return response()->json([
                    'error' => 'Approved bookings can only be completed or cancelled.',
                ], 422);
            }
        }

        // Approved -> Completed allowed, Approved -> Cancelled (admin) allowed
        $booking->status = $newStatus;
        
        // Update aesthetician note if provided
        if (isset($data['aesthetician_note'])) {
            $booking->aesthetician_note = $data['aesthetician_note'];
        }
        
        $booking->save();

        // If newly approved, auto-reject other pending bookings
        // with same aesthetician, date & time
        if ($newStatus === Booking::STATUS_APPROVED && $booking->aesthetician_id) {
            Booking::where('id', '!=', $booking->id)
                ->where('aesthetician_id', $booking->aesthetician_id)
                ->where('appointment_date', $booking->appointment_date)
                ->where('appointment_time', $booking->appointment_time)
                ->where('status', Booking::STATUS_PENDING)
                ->update(['status' => Booking::STATUS_REJECTED]);
        }

        return response()->json(['booking' => $booking]);
    }

    /**
     * Admin-only hard delete (optional).
     */
    public function destroy(Request $request, Booking $booking)
    {
        $user = auth()->user();

        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Only admin can delete bookings.'], 403);
        }

        $booking->delete();

        return response()->json(['message' => 'Booking deleted.']);
    }
}
