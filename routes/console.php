<?php

use AlertMetrics\DigestScheduler;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('alerthub:schedule-digests {project_id} {--date=} {--type=daily}', function (DigestScheduler $scheduler): int {
    $date = $this->option('date') ?: now()->toDateString();
    $type = (string) $this->option('type');
    $count = $scheduler->scheduleDigests((int) $this->argument('project_id'), $date, $type);

    $this->info("Scheduled {$count} {$type} digest(s) for {$date}.");

    return self::SUCCESS;
})->purpose('Schedule alert digest jobs for a project');
