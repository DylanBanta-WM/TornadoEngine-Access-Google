<?php

namespace TornadoEngine\AccessGoogle;

use Illuminate\Support\ServiceProvider;
use TornadoEngine\Access\Access;
use TornadoEngine\Cron\Cron;

class AccessGoogleServiceProvider extends ServiceProvider
{
    public const JOB = 'access-google.sync';

    public function boot(Access $access): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'access-google');
        $this->loadRoutesFrom(__DIR__.'/../routes/access-google.php');

        $access->tab('access.google.index', __('Google'), active: 'access.google.*');
        if (method_exists($access, 'userAction')) {
            $access->userAction('access-google::exclude-button');
        }

        $this->callAfterResolving(Cron::class, function (Cron $cron) {
            $cron->job(self::JOB, __('Sync roles from Google'), fn () => app(Sync::class)->run(),
                schedule: '30 2 * * *',
                description: __('Gives roles to the people in your Google rules (Users and roles → Google), and takes them back from people who left.'));
        });
    }
}
