<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// جدولة: دورة الاشتراكات اليومية
// Schedule::command('subscriptions:daily-cycle')->dailyAt('23:47');

// جدولة: تجديد الاشتراكات وإصدار الفواتير الدورية
Schedule::command('contracts:renew-and-bill --email=alfqyhb201@gmail.com')
    ->dailyAt('05:50')
    ->description('📊 تقرير التجديد التلقائي للاشتراكات وإصدار الفواتير - TrueERP')
    ->sendOutputTo('storage/logs/contracts-renew-and-bill.log');

Schedule::command('queue:work --stop-when-empty --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

// Take database backups daily at 1 AM
Schedule::command('backup:run --only-db')->dailyAt('01:00');
