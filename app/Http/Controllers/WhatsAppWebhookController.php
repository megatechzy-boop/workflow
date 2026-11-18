<?php

namespace App\Http\Controllers;

use App\Support\WhatsApp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/** Meta calls this (no login): GET once to check the verify token, then POST for every message / status */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $token = (string) config('services.whatsapp.verify_token');
        abort_unless($token !== '' && $request->query('hub_mode') === 'subscribe' && hash_equals($token, (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }

    public function receive(Request $request): Response
    {
        abort_unless(WhatsApp::validSignature($request->getContent(), $request->header('X-Hub-Signature-256')), 403);
        try {
            WhatsApp::handle($request->json()->all());
        } catch (Throwable $e) {
            report($e); // still 200: otherwise Meta keeps sending the same payload
        }

        return response('ok');
    }
}
