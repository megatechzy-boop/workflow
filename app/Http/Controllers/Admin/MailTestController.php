<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Admin button: send a test email to yourself to check the Resend setup (no terminal needed) */
class MailTestController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $to = $request->user()->email;

        try {
            Mail::raw("This is a test email from Social Workflow.\n\nIf you can read this, email notifications are working.", function ($m) use ($to) {
                $m->to($to)->subject('Social Workflow · test email');
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Email failed: '.Str::limit($e->getMessage(), 200).' Check MAIL_PASSWORD (Resend API key) and that the FROM domain is verified in Resend.');
        }

        return back()->with('status', 'Test email sent to '.$to.' (mailer: '.config('mail.default').'). Check the inbox and spam folder.');
    }
}
