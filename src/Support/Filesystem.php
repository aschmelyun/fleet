<?php

namespace Aschmelyun\Fleet\Support;

use Aschmelyun\Fleet\Fleet;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class Filesystem
{
    public function makeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    public function createSslDirectories(): void
    {
        $this->makeDirectory($this->getHomeDirectory().'/.config/mkcert/certs');
        $this->makeDirectory($this->getHomeDirectory().'/.config/mkcert/conf');
    }

    public function getHomeDirectory(): string
    {
        $homeDirectory = new Process(['sh', '-c', 'echo $HOME']);
        $homeDirectory->run();

        return trim($homeDirectory->getOutput());
    }

    public function writeToEnvFile(string $key, string $value): void
    {
        $file = base_path('.env');
        if (! file_exists($file)) {
            throw new \Exception('Application .env file is missing, can\'t continue');
        }

        $env = file_get_contents($file);
        $env = explode("\n", $env);

        $filteredEnvAppPort = array_filter($env, fn ($line) => str_starts_with($line, $key));
        if (! empty($filteredEnvAppPort)) {
            $env[key($filteredEnvAppPort)] = "{$key}={$value}";
        } else {
            $insert = ["{$key}={$value}"];
            array_splice($env, 5, 0, $insert);
        }

        file_put_contents(base_path('.env'), implode("\n", $env));
    }

    public function createCertificates(string $domain): void
    {
        $this->createSslDirectories();

        $process = Fleet::process('mkcert -install');
        if (! $process->isSuccessful()) {
            throw new \Exception('mkcert is not installed or configured incorrectly, please install it and try again');
        }

        $process = Fleet::process(
            "mkcert -cert-file {$this->getHomeDirectory()}/.config/mkcert/certs/{$domain}.crt -key-file {$this->getHomeDirectory()}/.config/mkcert/certs/{$domain}.key {$domain}"
        );
        if (! $process->isSuccessful()) {
            throw new \Exception('mkcert is not installed or configured incorrectly, please install it and try again');
        }
    }

    public function createSslConfig(string $domain): void
    {
        $sslConfigFile = "{$this->getHomeDirectory()}/.config/mkcert/conf/ssl.yml";
        $ssl = [];
        if (file_exists($sslConfigFile)) {
            $ssl = Yaml::parseFile($sslConfigFile);
        }

        $ssl['tls']['certificates'][] = [
            'certFile' => "/etc/traefik/certs/{$domain}.crt",
            'keyFile' => "/etc/traefik/certs/{$domain}.key",
        ];

        file_put_contents($sslConfigFile, Yaml::dump($ssl, 6));
    }
}
