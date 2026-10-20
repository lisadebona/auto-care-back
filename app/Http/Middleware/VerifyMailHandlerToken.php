<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMailHandlerToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('services.mailgun.handler_token');
        $provided = (string) $request->header('X-Mail-Handler-Token', '');

        if (! is_string($configured) || $configured === '' || ! hash_equals($configured, $provided)) {
            abort(401, 'Invalid mail handler token.');
        }

        return $next($request);
    }
}
