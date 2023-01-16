# Changelog

All notable changes to `aschmelyun/fleet` will be documented in this file.

## 1.0.2 - 2023-01-16

- Removes port bindings in `docker-compose.yml` for the app service
- Adds loadbalancer entry for traefik, bound to port 80
- Modifies the add command to use the first service name available, instead of assuming it's laravel.test

## 1.0.1 - 2023-01-15

- Adds `-a` flag when removing containers to search for all available ones
- Adds regex for filtering by exact name for fleet network and traefik container
- Fixes some capitalization

## 1.0.0 - 2023-01-15

- Initial release!
