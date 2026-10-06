<?php


namespace App\Services;

use App\Models\Client;

class ServiceHistoryService
{
    public function getClientServiceHistory(
        Client $client
    )
    {
        return $client->bookings()
            ->with([
                'bookingServices.service',
                'bookingServices.staff',
            ])
            ->latest('booking_date')
            ->get()
            ->flatMap(function ($booking) {

                return $booking->bookingServices->map(
                    function ($bookingService) use ($booking) {

                        return [
                            'booking_id' => $booking->id,
                            'booking_date' => $booking->booking_date,

                            'service_id' => $bookingService->service_id,
                            'service_name' => optional(
                                $bookingService->service
                            )->name,

                            'staff_id' => $bookingService->staff_id,
                            'staff_name' => optional(
                                $bookingService->staff
                            )->name,

                            'start_time' => $bookingService->start_time,
                            'end_time' => $bookingService->end_time,

                            'duration' => $bookingService->duration,

                            'service_price' => (float)
                            $bookingService->price,

                            'service_deposit_amount' => (float)
                            $bookingService->deposit_amount,

                            'booking_total_amount' => (float)
                            $booking->total_amount,

                            'booking_paid_amount' => (float)
                            $booking->paid_amount,

                            'booking_payment_status' =>
                                $booking->payment_status,

                            'booking_status' =>
                                $booking->status,
                        ];
                    }
                );
            })
            ->values();
    }
}
