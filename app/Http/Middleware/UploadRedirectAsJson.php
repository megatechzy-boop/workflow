<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Upload forms are sent by script (resources/js/upload-progress.js) to show a progress bar.
 * A redirect is answered as {"redirect": url} so the browser opens that page itself: the
 * flash message or validation errors then show there instead of being used up by the script.
 */
class UploadRedirectAsJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->hasHeader('X-Upload-Progress') && $response instanceof RedirectResponse) {
            // errors: validation failed, so a multi-file upload stops and shows them
            return response()->json(['redirect' => $response->getTargetUrl(), 'errors' => $request->session()->has('errors')]);
        }

        return $response;
    }
}
