<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetInterfaceLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', $request->user()?->locale ?? 'uz');
        app()->setLocale(in_array($locale, ['uz', 'uz_Cyrl', 'ru'], true) ? $locale : 'uz');

        return $next($request);
    }
}
