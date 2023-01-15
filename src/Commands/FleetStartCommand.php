<?php

namespace Aschmelyun\Fleet\Commands;

use Aschmelyun\Fleet\Fleet;
use Illuminate\Console\Command;

class FleetStartCommand extends Command
{
    public $signature = 'fleet:start';

    public $description = 'Starts up the Fleet network and Traefik container';

    public function handle(): int
    {
        // is the fleet docker network running? if not, start it up
        $process = Fleet::process('docker network ls --filter name=fleet --format {{.ID}}');

        if (!$process->getOutput()) {
            $this->info('No Fleet network, creating one...');

            $process = Fleet::process('docker network create fleet');
            if (!$process->isSuccessful()) {
                $this->error('Could not start Fleet Docker network');

                return self::FAILURE;
            }

            $this->line($process->getOutput());
        }

        // is the fleet traefik container running? if not, start it up
        $process = Fleet::process('docker ps -a --filter name=fleet --format {{.ID}}');

        if (!$process->getOutput()) {
            $this->info('No Fleet container, spinning it up...');
            $process = Fleet::process(
                'docker run -d -p 8080:8080 -p 80:80 --network=fleet -v /var/run/docker.sock:/var/run/docker.sock --name=fleet traefik:v2.9 --api.insecure=true --providers.docker',
                true
            );
        }

        return self::SUCCESS;
    }
}
