<?php

namespace StevenFox\LaravelModelValidation\Tests;

use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    public function getEnvironmentSetUp($app): void
    {
        config()->set('app.key', 'base64:KWmm+rhCpmYSG+Feh5hIjWWPvaBwQxjpLTYthMhVsUo=');
        config()->set('database.default', 'testing');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom('./Migrations');
    }
}
