<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarItemController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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
    Route::post('contents/{content}/review', [ContentController::class, 'review'])->name('contents.review');
    Route::post('contents/{content}/comments', [ContentController::class, 'comment'])->name('contents.comment');
    Route::post('contents/{content}/customer-review', [ContentController::class, 'customerReview'])->name('contents.customerReview');
    Route::post('contents/{content}/status', [ContentController::class, 'markStatus'])->name('contents.status');
    Route::get('media/{media}', [ContentController::class, 'media'])->name('media.show');
    Route::get('media/{media}/download', [ContentController::class, 'download'])->name('media.download');
    Route::post('contents/{content}/comments/{comment}/fixed', [ContentController::class, 'toggleFixed'])->name('contents.comments.fixed');
    Route::get('leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard');

    // Brand library (customer/team photos-videos)
    Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
    Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
    Route::get('assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
    Route::get('assets/{asset}/download', [AssetController::class, 'download'])->name('assets.download');
    Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');

    // Group chat (room = team | client:{id} | internal:{id})
    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('chat/{room}/messages', [ChatController::class, 'poll'])->where('room', '[a-z]+(:[0-9]+)?')->name('chat.poll');
    Route::post('chat/{room}/messages', [ChatController::class, 'store'])->where('room', '[a-z]+(:[0-9]+)?')->name('chat.store');
    Route::get('chat/attachment/{message}', [ChatController::class, 'attachment'])->name('chat.attachment');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::resource('calendars.items', CalendarItemController::class)->except(['index', 'show'])->scoped();
});

Route::middleware(['auth', 'permission:manage_clients'])->group(function () {
    Route::resource('clients', ClientController::class)->except('show');
});

Route::middleware(['auth', 'permission:manage_users'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class)->except('show');
});

// Only Admins can change role permissions
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::put('roles', [RoleController::class, 'update'])->name('roles.update');
});

require __DIR__.'/auth.php';
