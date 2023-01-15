<?php

namespace Aschmelyun\Fleet\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class FleetRemoveCommand extends Command
{
    public $signature = 'fleet:remove';

    public $description = 'Removes Fleet support from the current application';

    public function handle(): int
    {
        // determine if fleet is installed, return a response if not
        $file = base_path('docker-compose.yml');
        if (! file_exists($file)) {
            $this->error('A docker-compose.yml file does not exist');

            return self::FAILURE;
        }

        $yaml = Yaml::parseFile($file);
        if (! isset($yaml['networks']['fleet'])) {
            $this->info(' Fleet is not currently installed on this application');

            return self::SUCCESS;
        }

        // determine if the backup docker-compose.yml file exists
        $backup = base_path('docker-compose.backup.yml');
        if (file_exists($backup)) {
            if ($this->confirm('A backup docker-compose.yml file exists, would you like to just restore it?', true)) {
                rename(base_path('docker-compose.backup.yml'), base_path('docker-compose.yml'));
                $this->info(' The docker-compose.yml backup has been restored');

                return self::SUCCESS;
            }
        }

        // remove all fleet additions to the .env and docker-compose.yml files
        $file = base_path('.env');
        if (file_exists($file)) {
            $env = file_get_contents($file);
            $env = explode("\n", $env);

            foreach ($env as $index => $line) {
                if (str_starts_with($line, 'APP_PORT')) {
                    unset($env[$index]);
                }
            }

            file_put_contents(base_path('.env'), implode("\n", $env));
        }

        $service = $yaml['services'][array_keys($yaml['services'])[0]];
        unset($yaml['services'][array_keys($yaml['services'])[0]]);

        $yaml['services'] = ['laravel.test' => $service, ...$yaml['services']];

        $yaml['services']['laravel.test']['networks'] = ['sail'];
        unset($yaml['services']['laravel.test']['labels']);

        unset($yaml['networks']['fleet']);

        file_put_contents(base_path('docker-compose.yml'), Yaml::dump($yaml, 6));

        // return info back to the user
        $this->info(' ✨ All done! Fleet has been successfully removed from this application');

        return self::SUCCESS;
    }
}
