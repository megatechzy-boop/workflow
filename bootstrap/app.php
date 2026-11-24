<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\LogoutInactiveUser::class,
            \App\Http\Middleware\UploadRedirectAsJson::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
        $middleware->validateCsrfTokens(except: ['webhooks/*']); // Meta posts here, checked by signature
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // A form sent from an old tab or tapped twice (already done, or changed meanwhile), or a page left open
        // until the session ran out: the browser goes straight back to the page, which shows a popup, instead of
        // a bare 403 / 419 / 422 page. The status stays the same (nothing was done); opening a page (GET) keeps
        // its real 403 page, and script calls that want JSON (chat) get their status as before.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $code = $e->getStatusCode();
            $upload = $request->hasHeader('X-Upload-Progress');
            if ($request->isMethodSafe() || ! in_array($code, [403, 419, 422], true)
                || ($request->expectsJson() && ! $upload) || ($code !== 419 && ! $request->user())) {
                return null;
            }

            $message = $code === 419
                ? 'This page was open for too long. Please try again.'
                : 'This could not be done: it was already done, or it changed meanwhile. The page now shows the latest state.';
            $request->session()->flash('popup', $message);
            $back = url()->previous(route('dashboard'));
            if ($upload) { // upload-progress.js opens the page and stops the other files
                return response()->json(['redirect' => $back, 'errors' => true], $code);
            }

            return response('<!DOCTYPE html><meta charset="utf-8"><meta http-equiv="refresh" content="0;url='.e($back).'">'
                .'<title>Back</title><p style="font-family:sans-serif">'.e($message).' <a href="'.e($back).'">Back</a></p>', $code);
        });
    })->create();
