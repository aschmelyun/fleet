<?php

namespace Aschmelyun\Fleet\Commands;

use Aschmelyun\Fleet\Fleet;
use Aschmelyun\Fleet\Support\Filesystem;
use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class FleetAddCommand extends Command
{
    public $signature = 'fleet:add
                        {domain? : The test domain to use}
                        {--ssl : Include local SSL with mkcert}';

    public $description = 'Installs Fleet support onto the current application';

    public function handle(Filesystem $filesystem): int
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
            $this->line(' For more information, check out https://laravel.com/docs/sail#installation');
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

        // determine what port 8081+ is available and set it as the APP_PORT
        $port = 8081;
        while ($this->isPortTaken($port)) {
            $port++;
        }

        try {
            $filesystem->writeToEnvFile('APP_PORT', $port);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        // add a modified docker-compose.yml file to include traefik labels
        $file = base_path('docker-compose.backup.yml');
        if (!file_exists($file)) {
            $file = base_path('docker-compose.yml');
        }

        if (!file_exists($file)) {
            $this->error('A docker-compose.yml file or a docker-compose.backup.yml file does not exist');

            return self::FAILURE;
        }

        $yaml = $this->generateYamlForDockerCompose($file, $domain, $filesystem);

        // determine if the user wants to use SSL and add support if so
        if ($this->option('ssl')) {
            $this->info(' 🔒 Adding SSL support...');

            try {
                $filesystem->createCertificates($domain);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $this->line('For more information, try checking out the documentation at mkcert.dev');
                return self::FAILURE;
            }

            try {
                $filesystem->createSslConfig($domain);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }

            $heading = str_replace('.', '-', $domain);
            $yaml['services'][$heading]['labels'][] = "traefik.http.routers.{$heading}.tls=true";
        }

        file_put_contents(base_path('docker-compose.yml'), Yaml::dump($yaml, 6));

        // call fleet:start to determine if the fleet network and traefik container is up
        $this->call('fleet:start');

        // return info back to the user
        $this->info(' ✨ All done! You can now run `./vendor/bin/sail up`');
        $this->newLine();

        return self::SUCCESS;
    }

    private function generateYamlForDockerCompose(string $file, string $domain, Filesystem $filesystem): array
    {
        $yaml = Yaml::parseFile($file);

        $heading = str_replace('.', '-', $domain);

        // resets the top service key to the domain name
        $service = $yaml['services'][array_keys($yaml['services'])[0]];
        unset($yaml['services'][array_keys($yaml['services'])[0]]);

        // adds the entire services array back with the new domain key
        $yaml['services'] = [$heading => $service, ...$yaml['services']];

        // adds the traefik labels to the yaml file
        $yaml['services'][$heading]['networks'][] = 'fleet';
        $yaml['services'][$heading]['labels'] = [
            "traefik.http.routers.{$heading}.rule=Host(`{$domain}`)",
            "traefik.http.services.{$heading}.loadbalancer.server.port=80",
        ];

        // removes port binding for our app service
        unset($yaml['services'][$heading]['ports'][0]);
        $yaml['services'][$heading]['ports'] = array_values($yaml['services'][$heading]['ports']);

        // adds the fleet network
        $yaml['networks']['fleet']['external'] = true;

        return $yaml;
    }

    private function isPortTaken($port): bool
    {
        $process = Fleet::process("lsof -nP -iTCP:{$port} -sTCP:LISTEN");

        return boolval($process->getOutput());
    }
}
