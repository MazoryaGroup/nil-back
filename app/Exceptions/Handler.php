<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        // اگر درخواست API هست
        if ($request->is('api/*') || $request->expectsJson()) {

            // خطای Authentication
            if ($exception instanceof AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 401,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            // خطای Unauthorized
            if ($exception instanceof UnauthorizedHttpException) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 401,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            // خطای 404
            if ($exception instanceof NotFoundHttpException) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 404,
                    'message' => 'مسیر درخواستی یافت نشد.',
                ], 404);
            }

            // خطای Method Not Allowed
            if ($exception instanceof MethodNotAllowedHttpException) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 405,
                    'message' => 'متد درخواست نامعتبر است.',
                ], 405);
            }

            // سایر خطاها
            $statusCode = method_exists($exception, 'getStatusCode')
                ? $exception->getStatusCode()
                : 500;

            return response()->json([
                'success' => false,
                'statusCode' => $statusCode,
                'message' => $exception->getMessage() ?: 'خطای داخلی سرور.',
            ], $statusCode);
        }

        return parent::render($request, $exception);
    }
}
