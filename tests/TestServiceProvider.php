<?php

namespace Dashed\DashedPopups\Tests;

use Illuminate\Support\ServiceProvider;

/**
 * Minimal service provider for the package test environment. It only does
 * what the tests need: load the package migrations.
 *
 * The production DashedPopupsServiceProvider also wires up Filament plugins,
 * Livewire components, scheduled commands, and a global cms() helper from
 * dashed-core. None of those are needed to test the newsletter-sync job and
 * its supporting queries, and they would require dev dependencies that are
 * not installed in this package (filament/panels, dashed-core).
 */
class TestServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
