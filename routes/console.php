<?php

use Illuminate\Foundation\Inspiring;
use App\Models\Task;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deadline reminders. On cPanel run once a day via cron: php artisan tasks:remind
Artisan::command('tasks:remind', function () {
    $this->info(Task::sendDeadlineReminders().' reminder(s) sent.');
})->purpose('Remind members about tasks due tomorrow, today or overdue');

Schedule::command('tasks:remind')->dailyAt('09:00');
