<?php

use Illuminate\Foundation\Inspiring;
use App\Models\Task;
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

Schedule::command('tasks:remind')->dailyAt('09:00');

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
