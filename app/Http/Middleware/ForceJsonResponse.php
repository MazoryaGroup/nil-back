<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        // اگر درخواست API هست و Accept header مشخص نیست
        if ($request->is('api/*') && !$request->headers->has('Accept')) {
            $request->headers->set('Accept', 'application/json');
        }

        $response = $next($request);

        // اگر پاسخ HTML بود و درخواست API، تبدیل به JSON کن
        if ($request->is('api/*') &&
            str_contains($response->headers->get('Content-Type', ''), 'text/html')) {

            return response()->json([
                'is_status' => false,
                'statusCode' => $response->getStatusCode(),
                'message' => 'خطا در پردازش درخواست',
            ], $response->getStatusCode());
        }

        return $response;
    }
}
