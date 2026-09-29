<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        // اگر درخواست API هست
        if ($request->is('api/*') || $request->expectsJson()) {

            // خطای 404
            if ($exception instanceof NotFoundHttpException) {
                return response()->json([
                    'is_status' => false,
                    'statusCode' => 404,
                    'message' => 'مسیر درخواستی یافت نشد. لطفاً آدرس /api/login را بررسی کنید.'
                ], 404);
            }

            // خطای Method Not Allowed
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                return response()->json([
                    'is_status' => false,
                    'statusCode' => 405,
                    'message' => 'متد درخواست نامعتبر است'
                ], 405);
            }

            // سایر خطاها
            return response()->json([
                'is_status' => false,
                'statusCode' => method_exists($exception, 'getStatusCode')
                    ? $exception->getStatusCode()
                    : 500,
                'message' => $exception->getMessage() ?: 'خطای داخلی سرور',
            ], method_exists($exception, 'getStatusCode')
                ? $exception->getStatusCode()
                : 500);
        }

        return parent::render($request, $exception);
    }
}
