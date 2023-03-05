<?php

namespace Aschmelyun\Fleet\Commands;

use Aschmelyun\Fleet\Support\Filesystem;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class FleetRemoveCommand extends Command
{
    public $signature = 'fleet:remove';

    public $description = 'Removes Fleet support from the current application';

    public function handle(Filesystem $filesystem): int
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
        $filesystem->removeFromEnvFile('APP_PORT');
        $this->removeYamlFromDockerCompose($yaml);

        // return info back to the user
        $this->info(' ✨ All done! Fleet has been successfully removed from this application');

        return self::SUCCESS;
    }

    private function removeYamlFromDockerCompose(array $yaml): void
    {
        // remove the custom domain as the first service key
        $service = $yaml['services'][array_keys($yaml['services'])[0]];
        unset($yaml['services'][array_keys($yaml['services'])[0]]);

        // and replace it with the default, laravel.test
        $yaml['services'] = ['laravel.test' => $service, ...$yaml['services']];

        // reset the networks and labels
        $yaml['services']['laravel.test']['networks'] = ['sail'];
        unset($yaml['services']['laravel.test']['labels']);

        // reset the ports
        $yaml['services']['laravel.test']['ports'][] = '${APP_PORT:-80}:80';

        // remove the fleet network
        unset($yaml['networks']['fleet']);

        file_put_contents(base_path('docker-compose.yml'), Yaml::dump($yaml, 6));
    }
}
