<?php

use Database\Seeders\TestCatalogueSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('madina:backup')->dailyAt('02:00')->withoutOverlapping();

Artisan::command('madina:test-catalogue {--force : Allow migrations and test products in production}', function () {
    $options = ['--force' => (bool) $this->option('force')];
    $status = $this->call('migrate', $options);
    if ($status !== 0) {
        return $status;
    }

    return $this->call('db:seed', ['--class' => TestCatalogueSeeder::class, ...$options]);
})->purpose('Apply migrations then restore the three shared stock and catalogue test products');

Artisan::command('madina:mail-status', function () {
    $this->table(['Setting', 'Effective value'], [
        ['Mailer', config('mail.default')],
        ['Sender', config('mail.from.address')],
        ['Resend API key', filled(config('services.resend.key')) ? 'Configured (hidden)' : 'MISSING'],
        ['Resend SDK', class_exists(Resend::class) ? 'Installed' : 'MISSING'],
        ['Logging channel', config('logging.default')],
        ['Logging level', config('logging.channels.'.config('logging.default').'.level', 'channel-specific')],
        ['Configuration cached', app()->configurationIsCached() ? 'Yes' : 'No'],
        ['Customer schema', Schema::hasColumn('users', 'phone') && Schema::hasTable('email_verification_codes') ? 'Ready' : 'MIGRATIONS REQUIRED'],
    ]);
})->purpose('Show effective customer mail configuration without exposing credentials or sending email');
