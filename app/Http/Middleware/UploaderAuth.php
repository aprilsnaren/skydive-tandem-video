<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UploaderAuth
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Editors are trusted uploaders too — no second login needed.
        if (!$request->session()->get('uploader_authed') && !$request->session()->get('editor_authed')) {
            return redirect()->route('portal.login');
        }

        return $next($request);
    }
}
