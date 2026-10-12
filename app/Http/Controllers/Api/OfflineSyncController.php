<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OfflineSyncRequest;
use App\Models\User;
use App\Services\OfflineSyncService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class OfflineSyncController extends Controller
{
    private const ACTIONS = 'payment_pos,payment_cash,expense_create,cash_in,cash_out,refund_create';

    public function sync(Request $request, OfflineSyncService $service): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'offline_id' => ['required', 'string', 'max:100'],
            'action' => ['required', 'string', 'in:' . self::ACTIONS],
            'payload' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $operator = auth('operator')->user();
        if (!$operator) {
            return $this->error('Unauthenticated operator.', 401);
        }

        try {
            $action = (string) $request->input('action');
            $this->authorizeFinancialAction($operator, $action);

            $record = $service->process(
                null,
                (int) $operator->id,
                (string) $request->input('offline_id'),
                $action,
                $request->input('payload')
            );

            // An existing offline_id can return a failed or pending record.
            // Do not report financial success based only on the absence of an exception.
            return $this->operationResponse($record, 'Offline operation synced successfully.');
        } catch (AuthorizationException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            report($e);
            return $this->error('Unable to sync the offline operation.', 500);
        }
    }

    public function batch(Request $request, OfflineSyncService $service): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'operations' => ['required', 'array', 'min:1', 'max:100'],
            'operations.*.offline_id' => ['required', 'string', 'max:100'],
            'operations.*.action' => ['required', 'string', 'in:' . self::ACTIONS],
            'operations.*.payload' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $operator = auth('operator')->user();
        if (!$operator) {
            return $this->error('Unauthenticated operator.', 401);
        }

        $results = [];
        $counts = ['completed' => 0, 'failed' => 0, 'pending' => 0, 'processing' => 0];

        foreach ($request->input('operations') as $operation) {
            $offlineId = (string) $operation['offline_id'];
            $action = (string) $operation['action'];

            try {
                $this->authorizeFinancialAction($operator, $action);

                $record = $service->process(
                    null,
                    (int) $operator->id,
                    $offlineId,
                    $action,
                    $operation['payload']
                );

                $status = (string) $record->status;
                if (!array_key_exists($status, $counts)) {
                    $status = 'failed';
                }
                $counts[$status]++;

                $results[] = [
                    'offline_id' => $offlineId,
                    'action' => $action,
                    'status' => $status,
                    'message' => $record->error_message,
                    'data' => $this->formatSyncRequest($record),
                ];
            } catch (Throwable $e) {
                $counts['failed']++;

                if (!$e instanceof AuthorizationException && !$e instanceof \RuntimeException) {
                    report($e);
                }

                $record = OfflineSyncRequest::query()
                    ->where('offline_id', $offlineId)
                    ->where('user_id', (int) $operator->id)
                    ->first();

                $results[] = [
                    'offline_id' => $offlineId,
                    'action' => $action,
                    'status' => 'failed',
                    'message' => ($e instanceof AuthorizationException || $e instanceof \RuntimeException)
                        ? $e->getMessage()
                        : 'Unable to sync this operation.',
                    'data' => $record ? $this->formatSyncRequest($record) : null,
                ];
            }
        }

        $allCompleted = $counts['completed'] === count($results);

        return response()->json([
            'status' => $allCompleted,
            'statusCode' => 200,
            'message' => $allCompleted
                ? 'All offline operations synced successfully.'
                : 'Offline sync completed with operations requiring attention.',
            'summary' => array_merge(['total' => count($results)], $counts),
            'data' => $results,
        ]);
    }

    public function status(string $offlineId): JsonResponse
    {
        $operator = auth('operator')->user();
        if (!$operator) {
            return $this->error('Unauthenticated operator.', 401);
        }

        $record = OfflineSyncRequest::query()->where('offline_id', $offlineId)->first();
        if (!$record) {
            return $this->error('Offline sync request not found.', 404);
        }

        try {
            $this->authorizeRecordAccess($operator, $record);
            return response()->json([
                'status' => true,
                'statusCode' => 200,
                'message' => 'Offline sync request retrieved successfully.',
                'data' => $this->formatSyncRequest($record),
            ]);
        } catch (AuthorizationException $e) {
            return $this->error($e->getMessage(), 403);
        }
    }

    public function retry(string $offlineId, OfflineSyncService $service): JsonResponse
    {
        $operator = auth('operator')->user();
        if (!$operator) {
            return $this->error('Unauthenticated operator.', 401);
        }

        $record = OfflineSyncRequest::query()->where('offline_id', $offlineId)->first();
        if (!$record) {
            return $this->error('Offline sync request not found.', 404);
        }

        try {
            $this->authorizeRecordAccess($operator, $record);

            if ($record->status === 'completed') {
                return $this->operationResponse($record, 'Offline operation is already completed.');
            }

            if ($record->status !== 'failed') {
                return response()->json([
                    'status' => false,
                    'statusCode' => 409,
                    'message' => 'Only failed operations can be retried.',
                    'data' => $this->formatSyncRequest($record),
                ], 409);
            }

            $record = $service->retry($record);
            return $this->operationResponse($record, 'Offline operation retried successfully.');
        } catch (AuthorizationException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\RuntimeException $e) {
            $record->refresh();
            return response()->json([
                'status' => false,
                'statusCode' => 422,
                'message' => $e->getMessage(),
                'data' => $this->formatSyncRequest($record),
            ], 422);
        } catch (Throwable $e) {
            report($e);
            return $this->error('Unable to retry the offline operation.', 500);
        }
    }

    private function authorizeRecordAccess(User $operator, OfflineSyncRequest $record): void
    {
        if (!$operator->isAdmin() && (int) $record->user_id !== (int) $operator->id) {
            throw new AuthorizationException('You cannot access another operator request.');
        }

        $this->authorizeFinancialAction($operator, (string) $record->action);
    }

    private function authorizeFinancialAction(User $operator, string $action): void
    {
        if ($operator->isAdmin()) {
            return;
        }

        if ($operator->role === 'staff' && in_array($action, ['payment_pos', 'payment_cash'], true)) {
            return;
        }

        throw new AuthorizationException('You do not have permission to perform this financial operation.');
    }

    private function operationResponse(OfflineSyncRequest $record, string $successMessage): JsonResponse
    {
        $state = (string) $record->status;
        $httpCode = match ($state) {
            'completed' => 200,
            'failed' => 409,
            'pending', 'processing' => 202,
            default => 409,
        };

        return response()->json([
            'status' => $state === 'completed',
            'statusCode' => $httpCode,
            'message' => match ($state) {
                'completed' => $successMessage,
                'failed' => $record->error_message ?: 'Offline operation failed.',
                'pending', 'processing' => 'Offline operation is not completed yet.',
                default => 'Unknown offline operation status.',
            },
            'data' => $this->formatSyncRequest($record),
        ], $httpCode);
    }

    private function formatSyncRequest(OfflineSyncRequest $record): array
    {
        return [
            'id' => $record->id,
            'client_id' => $record->client_id,
            'user_id' => $record->user_id,
            'offline_id' => $record->offline_id,
            'action' => $record->action,
            'status' => $record->status,
            'payload' => $record->payload,
            'result' => $record->result,
            'error_message' => $record->error_message,
            'attempts' => $record->attempts,
            'processed_at' => $record->processed_at?->toDateTimeString(),
            'created_at' => $record->created_at?->toDateTimeString(),
            'updated_at' => $record->updated_at?->toDateTimeString(),
        ];
    }

    private function error(string $message, int $httpCode): JsonResponse
    {
        return response()->json([
            'status' => false,
            'statusCode' => $httpCode,
            'message' => $message,
        ], $httpCode);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'status' => false,
            'statusCode' => 422,
            'message' => 'Validation failed.',
            'errors' => $errors,
        ], 422);
    }
}
