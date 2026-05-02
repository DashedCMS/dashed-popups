<?php

namespace Dashed\DashedPopups\Tests;

use Orchestra\Testbench\TestCase as Orchestra;

/**
 * The full DashedPopupsServiceProvider depends on filament/panels (DashedPopupsPlugin
 * implements Filament\Contracts\Plugin) and the dashed-core helper bootstrap. Neither
 * is part of this package's dev dependencies. The tests here exercise the
 * SyncPopupSubmissionToNewsletterJob and Eloquent queries used by the sync feature,
 * so we register a minimal test provider that loads the package migrations and
 * skips the Filament plugin registration.
 */
class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            TestServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('queue.default', 'sync');
    }
}
