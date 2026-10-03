<?php


namespace App\Services;

use App\Models\Booking;
use App\Models\Notification;
use App\Models\Client;
use App\Models\User;

class NotificationService
{
    /**
     * Create a notification for a client.
     */
    public function notifyClient(
        Client   $client,
        string   $type,
        string   $title,
        string   $message,
        ?Booking $booking = null
    ): Notification
    {
        return Notification::create([
            'user_id' => null,
            'client_id' => $client->id,
            'booking_id' => $booking?->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Create a notification for a system user.
     */
    public function notifyUser(
        User     $user,
        string   $type,
        string   $title,
        string   $message,
        ?Booking $booking = null
    ): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'client_id' => null,
            'booking_id' => $booking?->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'is_read' => false,
            'read_at' => null,
        ]);
    }
}
