<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Prevent Symfony's Request::getContent() from reading the full multipart
 * body into memory via file_get_contents('php://input').
 *
 * PHP >= 5.6 keeps php://input available even for multipart/form-data.
 * By the time this middleware runs, PHP has already populated $_FILES and
 * $_POST, so clearing the raw content loses nothing — it just stops the
 * 500/OOM crash when uploading large video files.
 */
class ClearMultipartContent
{
    public function handle(Request $request, Closure $next)
    {
        if (str_contains($request->header('Content-Type', ''), 'multipart/form-data')) {
            $request->setContent('');
        }

        return $next($request);
    }
}
