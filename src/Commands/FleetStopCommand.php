<?php

namespace Aschmelyun\Fleet\Commands;

use Aschmelyun\Fleet\Fleet;
use Illuminate\Console\Command;

class FleetStopCommand extends Command
{
    public $signature = 'fleet:stop';

    public $description = 'Stops and removes the Fleet network and app containers';

    public function handle(): int
    {
        if (! $this->confirm('This will stop and remove all Sail instances running on the Fleet network, do you want to continue?')) {
            return self::SUCCESS;
        }

        // stop and remove all docker containers running on the fleet network
        $process = Fleet::process('docker ps -a --filter network=fleet --format {{.ID}}');

        $ids = explode("\n", $process->getOutput());
        foreach (array_filter($ids) as $id) {
            $this->line("Removing container {$id}");

            $process = Fleet::process("docker rm -f {$id}");
            if (! $process->isSuccessful()) {
                $this->error("Error removing container {$id}");

                return self::FAILURE;
            }
        }

        // remove the fleet docker network
        $process = Fleet::process('docker network rm fleet');

        $this->info(' Fleet has been successfully stopped and all active containers have been removed');

        return self::SUCCESS;
    }
}
