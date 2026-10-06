<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OfflineSyncRequest;
use App\Services\OfflineSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class OfflineSyncController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Single Sync
    |--------------------------------------------------------------------------
    */

    public function sync(
        Request $request,
        OfflineSyncService $offlineSyncService
    ): JsonResponse {

        $validator = Validator::make(
            $request->all(),
            [
                'offline_id' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'action' => [
                    'required',
                    'string',
                    'in:payment_pos,payment_cash,expense_create,cash_in,cash_out,refund_create',
                ],

                'payload' => [
                    'required',
                    'array',
                ],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'statusCode' => 422,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | Authenticated Operator
            |--------------------------------------------------------------------------
            */

            $operator = auth('operator')->user();

            if (!$operator) {
                return response()->json([
                    'status' => false,
                    'statusCode' => 401,
                    'message' => 'Unauthenticated operator.',
                ], 401);
            }

            /*
            |--------------------------------------------------------------------------
            | Internal Salon PWA
            |--------------------------------------------------------------------------
            |
            | client_id متعلق به مشتری است، نه اپراتور.
            | بنابراین در سطح Sync آن را null می‌گذاریم.
            | Payment / Refund مشتری را از خود Payment تشخیص می‌دهند.
            |
            */

            $clientId = null;
            $userId = (int) $operator->id;

            /*
            |--------------------------------------------------------------------------
            | Process
            |--------------------------------------------------------------------------
            */

            $syncRequest = $offlineSyncService->process(
                $clientId,
                $userId,
                $request->string('offline_id')->toString(),
                $request->string('action')->toString(),
                $request->input('payload')
            );

            return response()->json([
                'status' => true,
                'statusCode' => 200,
                'message' => 'Offline operation synced successfully.',
                'data' => $this->formatSyncRequest(
                    $syncRequest
                ),
            ]);

        } catch (Throwable $e) {

            return response()->json([
                'status' => false,
                'statusCode' => 422,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Batch Sync
    |--------------------------------------------------------------------------
    */

    public function batch(
        Request $request,
        OfflineSyncService $offlineSyncService
    ): JsonResponse {

        $validator = Validator::make(
            $request->all(),
            [
                'operations' => [
                    'required',
                    'array',
                    'min:1',
                    'max:100',
                ],

                'operations.*.offline_id' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'operations.*.action' => [
                    'required',
                    'string',
                    'in:payment_pos,payment_cash,expense_create,cash_in,cash_out,refund_create',
                ],

                'operations.*.payload' => [
                    'required',
                    'array',
                ],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'statusCode' => 422,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Authenticated Operator
        |--------------------------------------------------------------------------
        */

        $operator = auth('operator')->user();

        if (!$operator) {
            return response()->json([
                'status' => false,
                'statusCode' => 401,
                'message' => 'Unauthenticated operator.',
            ], 401);
        }

        $clientId = null;
        $userId = (int) $operator->id;

        $results = [];

        $completed = 0;
        $failed = 0;

        foreach (
            $request->input('operations')
            as $operation
        ) {

            try {

                $syncRequest = $offlineSyncService->process(
                    $clientId,
                    $userId,
                    (string) $operation['offline_id'],
                    (string) $operation['action'],
                    $operation['payload']
                );

                $results[] = [
                    'offline_id' =>
                        $operation['offline_id'],

                    'action' =>
                        $operation['action'],

                    'status' =>
                        'completed',

                    'data' =>
                        $this->formatSyncRequest(
                            $syncRequest
                        ),
                ];

                $completed++;

            } catch (Throwable $e) {

                $failedRequest = OfflineSyncRequest::query()
                    ->where(
                        'offline_id',
                        $operation['offline_id']
                    )
                    ->first();

                $results[] = [
                    'offline_id' =>
                        $operation['offline_id'],

                    'action' =>
                        $operation['action'],

                    'status' =>
                        'failed',

                    'message' =>
                        $e->getMessage(),

                    'data' =>
                        $failedRequest
                            ? $this->formatSyncRequest(
                            $failedRequest
                        )
                            : null,
                ];

                $failed++;
            }
        }

        return response()->json([
            'status' => $failed === 0,
            'statusCode' => 200,

            'message' => $failed === 0
                ? 'All offline operations synced successfully.'
                : 'Offline sync completed with some failed operations.',

            'summary' => [
                'total' =>
                    count($results),

                'completed' =>
                    $completed,

                'failed' =>
                    $failed,
            ],

            'data' => $results,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Sync Status
    |--------------------------------------------------------------------------
    */

    public function status(
        string $offlineId
    ): JsonResponse {

        $syncRequest = OfflineSyncRequest::query()
            ->where(
                'offline_id',
                $offlineId
            )
            ->first();

        if (!$syncRequest) {
            return response()->json([
                'status' => false,
                'statusCode' => 404,
                'message' => 'Offline sync request not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'statusCode' => 200,
            'message' => 'Offline sync request retrieved successfully.',
            'data' => $this->formatSyncRequest(
                $syncRequest
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Retry Failed Sync
    |--------------------------------------------------------------------------
    */

    public function retry(
        string $offlineId,
        OfflineSyncService $offlineSyncService
    ): JsonResponse {

        $syncRequest = OfflineSyncRequest::query()
            ->where(
                'offline_id',
                $offlineId
            )
            ->first();

        if (!$syncRequest) {
            return response()->json([
                'status' => false,
                'statusCode' => 404,
                'message' => 'Offline sync request not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Already Completed
        |--------------------------------------------------------------------------
        */

        if ($syncRequest->status === 'completed') {

            return response()->json([
                'status' => true,
                'statusCode' => 200,
                'message' => 'Offline operation is already completed.',
                'data' => $this->formatSyncRequest(
                    $syncRequest
                ),
            ]);
        }

        try {

            $syncRequest = $offlineSyncService
                ->retry(
                    $syncRequest
                );

            return response()->json([
                'status' => true,
                'statusCode' => 200,
                'message' => 'Offline operation retried successfully.',
                'data' => $this->formatSyncRequest(
                    $syncRequest
                ),
            ]);

        } catch (Throwable $e) {

            $syncRequest->refresh();

            return response()->json([
                'status' => false,
                'statusCode' => 422,
                'message' => $e->getMessage(),
                'data' => $this->formatSyncRequest(
                    $syncRequest
                ),
            ], 422);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Format Response
    |--------------------------------------------------------------------------
    */

    private function formatSyncRequest(
        OfflineSyncRequest $syncRequest
    ): array {

        return [
            'id' =>
                $syncRequest->id,

            'client_id' =>
                $syncRequest->client_id,

            'user_id' =>
                $syncRequest->user_id,

            'offline_id' =>
                $syncRequest->offline_id,

            'action' =>
                $syncRequest->action,

            'status' =>
                $syncRequest->status,

            'payload' =>
                $syncRequest->payload,

            'result' =>
                $syncRequest->result,

            'error_message' =>
                $syncRequest->error_message,

            'attempts' =>
                $syncRequest->attempts,

            'processed_at' =>
                $syncRequest->processed_at
                    ?->toDateTimeString(),

            'created_at' =>
                $syncRequest->created_at
                    ?->toDateTimeString(),

            'updated_at' =>
                $syncRequest->updated_at
                    ?->toDateTimeString(),
        ];
    }
}
