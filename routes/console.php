<?php

use Illuminate\Foundation\Inspiring;
use App\Models\MetaPost;
use App\Models\Task;
use App\Support\MetaInbox;
use App\Support\MetaPublisher;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 9 am task digest. On cPanel run once a day via cron: php artisan tasks:remind
Artisan::command('tasks:remind', function () {
    $this->info(Task::sendDeadlineReminders().' member(s) got their task list.');
})->purpose('Email each member one list of tasks overdue, due today or due tomorrow');

// Scheduled as callbacks (in the same PHP process): the host disables proc_open, which Schedule::command needs
Schedule::call(fn () => Artisan::call('tasks:remind'))->name('tasks-remind')->dailyAt('09:00');

// Facebook / Instagram posts queued from approved content (Instagram videos need a few checks)
Artisan::command('meta:publish', function () {
    $posts = MetaPost::whereIn('status', ['queued', 'processing'])
        ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
        ->with('content', 'page')->orderBy('id')->limit(10)->get();
    $posts->each(fn ($post) => MetaPublisher::process($post));
    $this->info($posts->count().' post(s) processed.');
})->purpose('Send queued Facebook / Instagram posts');
Schedule::call(fn () => Artisan::call('meta:publish'))->name('meta-publish')->everyMinute()->withoutOverlapping();

// New comments on our posts + new lead-form leads
Artisan::command('meta:sync', function () {
    $this->info(MetaInbox::syncComments().' comment(s), '.MetaInbox::syncLeads().' lead(s) added.');
})->purpose('Fetch new Facebook / Instagram comments and leads');
Schedule::call(fn () => Artisan::call('meta:sync'))->name('meta-sync')->everyTenMinutes()->withoutOverlapping();

// One-time clean-up before go-live: removes test work data, keeps users, roles, permissions and clients.
// No SSH on cPanel, so it runs from a one-time cron; the marker file stops a left-over cron from wiping real data.
Artisan::command('data:clear-work {--force}', function () {
    $marker = 'work-data-cleared';
    if (Storage::disk('local')->exists($marker)) {
        $this->error('Already done on '.Storage::disk('local')->get($marker).'. Remove the cron job.');

        return 1;
    }
    if (! $this->option('force')) {
        $this->error('This deletes calendars, content, tasks, chat, notifications, points and assets. Run with --force.');

        return 1;
    }

    $tables = ['point_entries', 'tasks', 'content_comments', 'content_media', 'content_versions', 'contents',
        'calendar_reviews', 'calendar_items', 'calendars', 'chat_reads', 'chat_messages', 'assets', 'notifications'];
    Schema::withoutForeignKeyConstraints(fn () => DB::transaction(function () use ($tables) {
        foreach ($tables as $table) {
            $this->line($table.': '.DB::table($table)->delete().' deleted');
        }
    }));
    foreach (['contents', 'chat', 'assets'] as $dir) {
        Storage::disk('local')->deleteDirectory($dir);
    }
    Storage::disk('local')->put($marker, now()->toDateTimeString());
    $this->info('Done. Users, roles, permissions and clients kept.');
})->purpose('One-time: delete test work data, keep users, roles and clients');
