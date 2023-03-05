<?php

namespace Aschmelyun\Fleet\Commands;

use Aschmelyun\Fleet\Support\Docker;
use Aschmelyun\Fleet\Support\Filesystem;
use Illuminate\Console\Command;

class FleetStartCommand extends Command
{
    public $signature = 'fleet:start';

    public $description = 'Starts up the Fleet network and Traefik container';

    public function handle(Filesystem $filesystem, Docker $docker): int
    {
        // is the fleet docker network running? if not, start it up
        if (!$docker->getNetwork('fleet')) {
            $this->info('No Fleet network, creating one...');

            try {
                $id = $docker->createNetwork('fleet');
            } catch (\Exception $e) {
                $this->error('Could not start Fleet Docker network');
                $this->line($e->getMessage());

                return self::FAILURE;
            }

            $this->line($id);
        }

        // just in case the mkcert directory doesn't exist, create it
        $filesystem->createSslDirectories();

        // is the fleet traefik container running? if not, start it up
        if (!$docker->getContainer('fleet')) {
            $this->info('No Fleet container, spinning it up...');

            try {
                $docker->startFleetTraefikContainer();
            } catch (\Exception $e) {
                $this->error('Could not start Fleet Traefik container');
                $this->line($e->getMessage());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
