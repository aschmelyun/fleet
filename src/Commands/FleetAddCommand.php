<?php

namespace Aschmelyun\Fleet\Commands;

use Aschmelyun\Fleet\Fleet;
use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class FleetAddCommand extends Command
{
    public $signature = 'fleet:add {domain?}';

    public $description = 'Installs Fleet support onto the current application';

    public function handle(): int
    {
        // set the domain to the one the user provided, or ask what it should be
        $domain = $this->argument('domain');
        if (!$domain) {
            $domain = $this->ask('What domain name would you like to use for this app?', 'laravel.localhost');
        }

        $domainArray = explode('.', $domain);
        if (end($domainArray) !== 'localhost') {
            $this->line(' Just a quick note: You may need to update your hosts file to direct traffic to this domain. You can prevent this by using a domain that ends in `.localhost`');
        }

        // determine if laravel/sail is a required-dev package in the root composer file
        if (!InstalledVersions::isInstalled('laravel/sail')) {
            $this->error(' Laravel Sail is required for this package');
            $this->line(' For more information, check out https://laravel.com/docs/9.x/sail#installation');
        }

        // if the docker-compose.yml file isn't available, publish it
        if (!file_exists(base_path('docker-compose.yml')) && !file_exists(base_path('docker-compose.backup.yml'))) {
            $this->info('No docker-compose.yml file available, running sail:install...');
            $this->call('sail:install');
        }

        // copy the docker-compose.yml file for a backup
        if (file_exists(base_path('docker-compose.yml'))) {
            if ($this->confirm('This will modify your docker-compose.yml file, do you want to back it up first?')) {
                rename(base_path('docker-compose.yml'), base_path('docker-compose.backup.yml'));
            }
        }

        // determine what port 8081+ is available
        $port = 8081;
        while ($this->isPortTaken($port)) {
            $port++;
        }

        $file = base_path('.env');
        if (!file_exists($file)) {
            $this->error("Application .env file is missing, can't continue");

            return self::FAILURE;
        }

        $env = file_get_contents($file);
        $env = explode("\n", $env);

        $filteredEnvAppPort = array_filter($env, fn ($line) => str_starts_with($line, 'APP_PORT'));
        if (!empty($filteredEnvAppPort)) {
            $env[key($filteredEnvAppPort)] = "APP_PORT={$port}";
        } else {
            $insert = ["APP_PORT={$port}"];
            array_splice($env, 5, 0, $insert);
        }

        file_put_contents(base_path('.env'), implode("\n", $env));

        // add a modified docker-compose.yml file to include traefik labels
        $file = base_path('docker-compose.backup.yml');
        if (!file_exists($file)) {
            $file = base_path('docker-compose.yml');
        }

        if (!file_exists($file)) {
            $this->error('A docker-compose.yml file or a docker-compose.backup.yml file does not exist');

            return self::FAILURE;
        }

        $yaml = Yaml::parseFile($file);

        $heading = str_replace('.', '-', $domain);

        $service = $yaml['services'][array_keys($yaml['services'])[0]];
        unset($yaml['services'][array_keys($yaml['services'])[0]]);

        $yaml['services'] = [$heading => $service, ...$yaml['services']];

        $yaml['services'][$heading]['networks'][] = 'fleet';
        $yaml['services'][$heading]['labels'] = [
            "traefik.http.routers.{$heading}.rule=Host(`{$domain}`)",
            "traefik.http.services.{$heading}.loadbalancer.server.port=80",
        ];

        unset($yaml['services'][$heading]['ports'][0]);

        $yaml['networks']['fleet']['external'] = true;

        file_put_contents(base_path('docker-compose.yml'), Yaml::dump($yaml, 6));

        // call fleet:start to determine if the fleet network and traefik container is up
        $this->call('fleet:start');

        // return info back to the user
        $this->info(' ✨ All done! You can now run `./vendor/bin/sail up`');
        $this->newLine();

        return self::SUCCESS;
    }

    private function isPortTaken($port): bool
    {
        $process = Fleet::process("lsof -nP -iTCP:{$port} -sTCP:LISTEN");

        return boolval($process->getOutput());
    }
}
