<?php

declare(strict_types=1);

/**
 * Manifest
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package;

final readonly class Manifest
{
    /**
     * @param array<string, PackageInfo> $packages Package name => info
     * @param string $generatedAt ISO 8601 timestamp
     */
    public function __construct(
        public array $packages,
        public string $generatedAt,
    ) {}

    /**
     * @return list<string>
     */
    public function packageNames(): array
    {
        return array_keys($this->packages);
    }

    /**
     * @return array<string, string> class => scope
     */
    public function allServices(): array
    {
        $services = [];

        foreach ($this->packages as $info) {
            foreach ($info->services as $class => $scope) {
                $services[$class] = $scope;
            }
        }

        return $services;
    }

    /**
     * @return array<string, string> interface => implementation
     */
    public function allBindings(): array
    {
        $bindings = [];

        foreach ($this->packages as $info) {
            foreach ($info->bindings as $interface => $implementation) {
                $bindings[$interface] = $implementation;
            }
        }

        return $bindings;
    }

    /**
     * @return array<string, string> class => config name
     */
    public function allConfigs(): array
    {
        $configs = [];

        foreach ($this->packages as $info) {
            foreach ($info->configs as $class => $name) {
                $configs[$class] = $name;
            }
        }

        return $configs;
    }
}
