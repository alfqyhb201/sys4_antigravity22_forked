<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserLastActivity
{
    /**
     * تحديث آخر نشاط للمستخدم عند كل طلب
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();
            Cache::put('user_last_activity_'.$user->id, [
                'timestamp' => now()->timestamp,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ], now()->addYear());
        }

        return $next($request);
    }
}
