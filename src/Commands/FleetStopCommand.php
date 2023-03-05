<?php

namespace Aschmelyun\Fleet\Commands;

use Aschmelyun\Fleet\Fleet;
use Aschmelyun\Fleet\Support\Docker;
use Illuminate\Console\Command;

class FleetStopCommand extends Command
{
    public $signature = 'fleet:stop';

    public $description = 'Stops and removes the Fleet network and app containers';

    public function handle(Docker $docker): int
    {
        if (! $this->confirm('This will stop and remove all Sail instances running on the Fleet network, do you want to continue?')) {
            return self::SUCCESS;
        }

        // stop and remove all docker containers running on the fleet network
        try {
            $docker->removeContainers('fleet');
        } catch (\Exception $e) {
            $this->error('Could not remove Fleet containers');
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        // remove the fleet docker network
        try {
            $docker->removeNetwork('fleet');
        } catch (\Exception $e) {
            $this->error('Could not remove Fleet network');
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        $this->info(' Fleet has been successfully stopped and all active containers have been removed');

        return self::SUCCESS;
    }
}
