<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    /**
     * Get all active services.
     */
    public function index(): JsonResponse
    {
        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'description',
                'duration',
                'price',
                'deposit_amount',
            ]);

        return response()->json([
            'status' => true,
            'statusCode' => 200,
            'message' => 'Services retrieved successfully.',
            'data' => $services,
        ], 200);
    }

    /**
     * Get single active service.
     */
    public function show(int $id): JsonResponse
    {
        $service = Service::query()
            ->where('is_active', true)
            ->where('id', $id)
            ->first();

        if (!$service) {
            return response()->json([
                'status' => false,
                'statusCode' => 404,
                'message' => 'Service not found.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => true,
            'statusCode' => 200,
            'message' => 'Service retrieved successfully.',
            'data' => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'duration' => $service->duration,
                'price' => $service->price,
                'deposit_amount' => $service->deposit_amount,
            ],
        ], 200);
    }
}
