<?php

namespace Aschmelyun\Fleet\Commands;

use Aschmelyun\Fleet\Support\Filesystem;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class FleetSslCommand extends Command
{
    public $signature = 'fleet:ssl
                        {domain? : The test domain to use}
                        {--add : Add TLS labels to the docker-compose.yml file}';

    public $description = 'Adds SSL support for an existing Fleet installation';

    public function handle(Filesystem $filesystem): int
    {
        // set the domain to the one the user provided, or ask what it should be
        $domain = $this->argument('domain');
        if (! $domain) {
            $domain = $this->ask('What domain name would you like to use for this app?', 'laravel.localhost');
        }

        $file = base_path('docker-compose.yml');
        if (! file_exists($file)) {
            $this->error('A docker-compose.yml file does not exist');

            return self::FAILURE;
        }

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

        if ($this->option('add')) {
            $this->addTlsToDockerCompose($file, $domain);
        }

        // return info back to the user
        $this->info(' ✨ All done! You can now run `./vendor/bin/sail up`');
        $this->newLine();

        return self::SUCCESS;
    }

    private function addTlsToDockerCompose(string $file, string $domain): void
    {
        $yaml = Yaml::parseFile($file);
        $heading = str_replace('.', '-', $domain);

        $yaml['services'][$heading]['labels'][] = "traefik.http.routers.{$heading}.tls=true";

        file_put_contents(base_path('docker-compose.yml'), Yaml::dump($yaml, 6));
    }
}
