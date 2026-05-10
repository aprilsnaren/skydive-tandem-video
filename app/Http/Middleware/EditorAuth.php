<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EditorAuth
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (!$request->session()->get('editor_authed')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
