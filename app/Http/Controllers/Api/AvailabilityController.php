<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\BookingAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AvailabilityController extends Controller
{
    public function __construct(
        protected BookingAvailabilityService $availabilityService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => [
                'nullable',
                'integer',
                'exists:staff,id',
            ],
            'service_id' => [
                'required',
                'integer',
                'exists:services,id',
            ],
            'date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'interval' => [
                'nullable',
                'integer',
                'min:5',
                'max:120',
            ],
        ]);

        try {
            $service = Service::query()
                ->whereKey($validated['service_id'])
                ->where('is_active', true)
                ->first();

            if (!$service) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 404,
                    'message' => 'Service not found or inactive.',
                ], 404);
            }

            $interval = (int) ($validated['interval'] ?? 30);

            /*
            |--------------------------------------------------------------------------
            | Find Eligible Staff
            |--------------------------------------------------------------------------
            */

            $staffQuery = DB::table('staff_services')
                ->join(
                    'staff',
                    'staff.id',
                    '=',
                    'staff_services.staff_id'
                )
                ->where(
                    'staff_services.service_id',
                    $service->id
                )
                ->where(
                    'staff_services.is_active',
                    true
                )
                ->where(
                    'staff.is_active',
                    true
                )
                ->select([
                    'staff.id as staff_id',
                    'staff_services.duration as custom_duration',
                ]);

            /*
            |--------------------------------------------------------------------------
            | Optional Staff Selection
            |--------------------------------------------------------------------------
            */

            if (!empty($validated['staff_id'])) {
                $staffQuery->where(
                    'staff.id',
                    (int) $validated['staff_id']
                );
            }

            $staffMembers = $staffQuery
                ->distinct()
                ->get();

            /*
            |--------------------------------------------------------------------------
            | Calculate Available Slots
            |--------------------------------------------------------------------------
            */

            $availableSlots = collect();

            foreach ($staffMembers as $staffMember) {

                $duration = (int) (
                $staffMember->custom_duration
                    ?: $service->duration
                );

                if ($duration <= 0) {
                    continue;
                }

                $staffSlots = $this->availabilityService
                    ->getAvailableSlots(
                        staffId: (int) $staffMember->staff_id,
                        date: $validated['date'],
                        duration: $duration,
                        interval: $interval
                    );

                foreach ($staffSlots as $slot) {

                    $key = $slot['start_time']
                        . '|'
                        . $slot['end_time'];

                    if (!$availableSlots->has($key)) {
                        $availableSlots->put($key, [
                            'start_time' => $slot['start_time'],
                            'end_time' => $slot['end_time'],
                            'duration' => $duration,
                            'staff_ids' => [],
                        ]);
                    }

                    $currentSlot = $availableSlots->get($key);

                    $currentSlot['staff_ids'][] =
                        (int) $staffMember->staff_id;

                    $currentSlot['staff_ids'] = array_values(
                        array_unique(
                            $currentSlot['staff_ids']
                        )
                    );

                    $availableSlots->put(
                        $key,
                        $currentSlot
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Sort and Return
            |--------------------------------------------------------------------------
            */

            $slots = $availableSlots
                ->values()
                ->sort(function ($a, $b) {
                    return strcmp(
                        $a['start_time'] . '|' . $a['end_time'],
                        $b['start_time'] . '|' . $b['end_time']
                    );
                })
                ->values();

            return response()->json([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Available slots retrieved successfully.',
                'data' => $slots,
            ], 200);

        } catch (Throwable $e) {

            Log::error('Availability API failed', [
                'service_id' => $validated['service_id'],
                'staff_id' => $validated['staff_id'] ?? null,
                'date' => $validated['date'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'statusCode' => 500,
                'message' => 'Failed to retrieve available slots.',
            ], 500);
        }
    }
}
