<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Phone/browser push: the service worker's subscription is saved per user */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'], $data['contentEncoding'] ?? 'aes128gcm');

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $request->validate(['endpoint' => ['required', 'string', 'max:500']]);
        $request->user()->deletePushSubscription($request->input('endpoint'));

        return response()->noContent();
    }
}
