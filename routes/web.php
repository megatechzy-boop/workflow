<?php

use App\Http\Controllers\Admin\MailTestController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\SalaryController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AdController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AudienceController;
use App\Http\Controllers\PixelController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarImportController;
use App\Http\Controllers\CalendarItemController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\CustomerBillController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MetaController;
use App\Http\Controllers\MetaInboxController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Public forms (no login): website enquiry, and each client's private onboarding link
Route::view('privacy', 'legal.privacy')->name('privacy');

// Meta fetches post media from short-lived signed links (no login)
Route::get('meta/media/{media}', [MetaController::class, 'media'])->middleware('signed')->name('meta.media');
Route::get('enquiry', [LeadController::class, 'form'])->name('enquiry.form');
Route::post('enquiry', [LeadController::class, 'submit'])->middleware('throttle:5,1')->name('enquiry.submit');
Route::get('enquiry/thanks', [LeadController::class, 'thanks'])->name('enquiry.thanks');
Route::get('onboarding/thanks', [OnboardingController::class, 'thanks'])->name('onboarding.thanks');
Route::get('onboarding/{token}', [OnboardingController::class, 'form'])->name('onboarding.form');
Route::post('onboarding/{token}', [OnboardingController::class, 'submit'])->middleware('throttle:10,1')->name('onboarding.submit');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Content calendar (permissions in CalendarPolicy)
    Route::resource('calendars', CalendarController::class)->except(['edit', 'update']);
    Route::post('calendars/{calendar}/submit', [CalendarController::class, 'submit'])->name('calendars.submit');
    Route::post('calendars/{calendar}/review', [CalendarController::class, 'review'])->name('calendars.review');
    Route::post('calendars/{calendar}/items/{item}/review', [CalendarController::class, 'reviewItem'])->scopeBindings()->name('calendars.items.review');
    Route::post('calendars/{calendar}/assign', [TaskController::class, 'assign'])->name('calendars.assign');
    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');

    // Content upload + approval (permissions in ContentPolicy)
    Route::post('tasks/{task}/contents', [ContentController::class, 'store'])->name('tasks.contents.store');
    Route::get('contents', [ContentController::class, 'index'])->name('contents.index');
    Route::get('contents/{content}', [ContentController::class, 'show'])->name('contents.show');
    Route::post('contents/{content}/resubmit', [ContentController::class, 'resubmit'])->name('contents.resubmit');
    Route::post('contents/{content}/replace', [ContentController::class, 'replace'])->name('contents.replace');
    Route::post('contents/{content}/review', [ContentController::class, 'review'])->name('contents.review');
    Route::post('contents/{content}/comments', [ContentController::class, 'comment'])->name('contents.comment');
    Route::post('contents/{content}/caption', [ContentController::class, 'updateCaption'])->name('contents.caption');
    Route::post('contents/{content}/customer-review', [ContentController::class, 'customerReview'])->name('contents.customerReview');
    Route::post('contents/{content}/status', [ContentController::class, 'markStatus'])->name('contents.status');
    Route::post('contents/{content}/meta', [MetaController::class, 'publish'])->name('contents.meta');
    Route::delete('contents/{content}/meta', [MetaController::class, 'unschedule'])->name('contents.meta.unschedule');
    Route::post('meta-posts/{metaPost}/boost', [MetaController::class, 'boost'])->name('contents.boost');
    // Paths avoid the words "ads" and "pixel": ad blockers stop such links and the app then shows "offline"
    Route::get('campaigns', [AdController::class, 'index'])->name('ads.index');
    Route::get('campaigns/create', [AdController::class, 'create'])->name('ads.create');
    Route::post('campaigns', [AdController::class, 'store'])->name('ads.store');
    Route::post('campaigns/{ad}/status', [AdController::class, 'status'])->name('ads.status');
    Route::get('audiences', [AudienceController::class, 'index'])->name('ads.audiences');
    Route::post('audiences', [AudienceController::class, 'store'])->name('ads.audiences.store');
    Route::delete('audiences/{audience}', [AudienceController::class, 'destroy'])->name('ads.audiences.destroy');
    Route::get('site-code', [PixelController::class, 'index'])->name('ads.pixel');
    Route::post('site-code/{client}', [PixelController::class, 'store'])->name('ads.pixel.store');
    Route::post('site-code/{client}/conversions', [PixelController::class, 'conversion'])->name('ads.pixel.conversion');
    Route::get('comments', [MetaInboxController::class, 'comments'])->name('meta.comments');
    Route::post('comments/{comment}/reply', [MetaInboxController::class, 'reply'])->name('meta.comments.reply');
    Route::get('meta-leads', [MetaInboxController::class, 'leads'])->name('meta.leads');
    Route::put('meta-leads/{lead}', [MetaInboxController::class, 'updateLead'])->name('meta.leads.update');
    Route::get('media/{media}', [ContentController::class, 'media'])->name('media.show');
    Route::get('media/{media}/download', [ContentController::class, 'download'])->name('media.download');
    Route::post('contents/{content}/comments/{comment}/fixed', [ContentController::class, 'toggleFixed'])->name('contents.comments.fixed');
    Route::get('leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard');
    Route::get('schedule', [ScheduleController::class, 'index'])->name('schedule');
    Route::get('reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
    Route::get('reports/posting', [ReportController::class, 'posting'])->name('reports.posting');
    Route::get('reports/campaigns', [ReportController::class, 'ads'])->name('reports.ads');
    Route::get('reports/leads', [ReportController::class, 'leads'])->name('reports.leads');
    Route::get('calendars/{calendar}/import', [CalendarImportController::class, 'create'])->name('calendars.import');
    Route::post('calendars/{calendar}/import', [CalendarImportController::class, 'store'])->name('calendars.import.store');

    // Brand library (customer/team photos-videos)
    Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
    Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
    Route::get('assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
    Route::get('assets/{asset}/download', [AssetController::class, 'download'])->name('assets.download');
    Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');

    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.checkIn');
    Route::post('attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.checkOut');

    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push.store');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');

    // Group chat (room = team | client:{id} | internal:{id})
    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('chat/{room}/messages', [ChatController::class, 'poll'])->where('room', '[a-z]+(:[0-9]+)?')->name('chat.poll');
    Route::post('chat/{room}/messages', [ChatController::class, 'store'])->where('room', '[a-z]+(:[0-9]+)?')->name('chat.store');
    Route::get('chat/attachment/{message}', [ChatController::class, 'attachment'])->name('chat.attachment');
    Route::delete('chat/message/{message}', [ChatController::class, 'destroy'])->name('chat.destroy');
    Route::get('chat/message/{message}/seen', [ChatController::class, 'seen'])->name('chat.seen');
    Route::post('chat/message/{message}/pin', [ChatController::class, 'pin'])->name('chat.pin');
    Route::post('chat/{room}/mute', [ChatController::class, 'mute'])->where('room', '[a-z]+(:[0-9]+)?')->name('chat.mute');
    Route::get('chat/{room}/posts', [ChatController::class, 'posts'])->where('room', '[a-z]+(:[0-9]+)?')->name('chat.posts');
    Route::get('chat/{room}/media', [ChatController::class, 'media'])->where('room', '[a-z]+(:[0-9]+)?')->name('chat.media');

    Route::get('my-bills/{invoice}', [CustomerBillController::class, 'invoice'])->name('my.invoice');
    Route::get('my-receipts/{payment}', [CustomerBillController::class, 'receipt'])->name('my.receipt');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::resource('calendars.items', CalendarItemController::class)->except(['index', 'show'])->scoped();
});

Route::middleware(['auth', 'permission:manage_clients'])->group(function () {
    Route::resource('clients', ClientController::class)->except('show');
    Route::post('clients/{client}/onboarding-link', [OnboardingController::class, 'makeLink'])->name('clients.onboardingLink');
    Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
    Route::put('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
});

// Client brief: also for Social Media Managers (checked in the controller)
Route::get('clients/{client}/brief', [OnboardingController::class, 'brief'])->middleware('auth')->name('clients.brief');

Route::middleware(['auth', 'permission:manage_users'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class)->except('show');
});

// Only Admins can change role permissions
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::put('roles', [RoleController::class, 'update'])->name('roles.update');
    Route::post('mail-test', MailTestController::class)->name('mail.test');
    Route::resource('packages', PackageController::class)->except('show');
    Route::get('meta', [MetaController::class, 'index'])->name('meta.index');
    Route::get('meta/connect', [MetaController::class, 'connect'])->name('meta.connect');
    Route::get('meta/callback', [MetaController::class, 'callback'])->name('meta.callback');

    // Module 7: office (Admin only)
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('attendance', [AttendanceController::class, 'update'])->name('attendance.update');
    Route::get('salary', [SalaryController::class, 'index'])->name('salary.index');
    Route::put('salary/{user}', [SalaryController::class, 'setSalary'])->name('salary.set');
    Route::post('salary/{user}/pay', [SalaryController::class, 'pay'])->name('salary.pay');
    Route::delete('salary/{user}/pay', [SalaryController::class, 'unpay'])->name('salary.unpay');
    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('billing/generate', [BillingController::class, 'generate'])->name('billing.generate');
    Route::get('billing/create', [BillingController::class, 'create'])->name('billing.create');
    Route::post('billing', [BillingController::class, 'store'])->name('billing.store');
    Route::get('billing/{invoice}', [BillingController::class, 'show'])->name('billing.show');
    Route::delete('billing/{invoice}', [BillingController::class, 'destroy'])->name('billing.destroy');
    Route::put('billing/{invoice}/amount', [BillingController::class, 'amount'])->name('billing.amount');
    Route::post('billing/{invoice}/payments', [BillingController::class, 'pay'])->name('billing.pay');
    Route::get('receipts/{payment}', [BillingController::class, 'receipt'])->name('billing.receipt');
    Route::delete('receipts/{payment}', [BillingController::class, 'destroyPayment'])->name('billing.receipt.destroy');
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
});

require __DIR__.'/auth.php';
