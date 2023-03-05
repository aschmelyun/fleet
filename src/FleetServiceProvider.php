<?php

namespace Aschmelyun\Fleet;

use Aschmelyun\Fleet\Commands\FleetAddCommand;
use Aschmelyun\Fleet\Commands\FleetRemoveCommand;
use Aschmelyun\Fleet\Commands\FleetStartCommand;
use Aschmelyun\Fleet\Commands\FleetStopCommand;
use Aschmelyun\Fleet\Support\Docker;
use Aschmelyun\Fleet\Support\Filesystem;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FleetServiceProvider extends PackageServiceProvider
{
    public function registeringPackage()
    {
        $this->app->singleton(Docker::class);
        $this->app->singleton(Filesystem::class);
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('fleet')
            ->hasConfigFile()
            ->hasCommands([
                FleetAddCommand::class,
                FleetRemoveCommand::class,
                FleetStartCommand::class,
                FleetStopCommand::class,
            ]);
    }
}
