<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow Scramble OpenAPI generation enough time on cold docs requests.
 */
final class IncreaseDocsTimeLimit
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(180);
        }

        return $next($request);
    }
}
