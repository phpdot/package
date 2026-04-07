<?php

declare(strict_types=1);

/**
 * Config File Generator
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Generator;

use PHPdot\Package\Scanner\ScannedClass;
use ReflectionClass;

final class ConfigFileGenerator
{
    /**
     * @param list<ScannedClass> $classes
     * @return list<string> Generated file paths
     */
    public function generate(array $classes, string $configPath): array
    {
        $generated = [];

        foreach ($classes as $scanned) {
            if ($scanned->configName === null) {
                continue;
            }

            $filePath = rtrim($configPath, '/') . '/' . $scanned->configName . '.php';

            if (is_file($filePath)) {
                continue;
            }

            if (!is_dir($configPath)) {
                mkdir($configPath, 0755, true);
            }

            $content = $this->generateFile($scanned);
            file_put_contents($filePath, $content);
            $generated[] = $filePath;
        }

        return $generated;
    }

    private function generateFile(ScannedClass $scanned): string
    {
        $lines = [];
        $lines[] = "<?php\n";
        $lines[] = "\n/**";
        $lines[] = "\n * Configuration for {$scanned->package}";
        $lines[] = "\n *";
        $lines[] = "\n * Class: {$scanned->class}";
        $lines[] = "\n *";
        $lines[] = "\n * Edit values below. This file is auto-generated once.";
        $lines[] = "\n * It will NOT be overwritten on update.";
        $lines[] = "\n */\n";
        $lines[] = "\nreturn [\n";

        $ref = new ReflectionClass($scanned->class);
        $constructor = $ref->getConstructor();

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();
                $type = $param->getType();
                $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed';

                $default = $param->isDefaultValueAvailable()
                    ? $this->formatDefault($param->getDefaultValue())
                    : "''";

                $lines[] = "    // ({$typeName})\n";
                $lines[] = "    '{$name}' => {$default},\n\n";
            }
        }

        $lines[] = "];\n";

        return implode('', $lines);
    }

    private function formatDefault(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }

        if (is_array($value)) {
            return $this->formatArray($value);
        }

        return "''";
    }

    /**
     * @param array<mixed> $value
     */
    private function formatArray(array $value): string
    {
        if ($value === []) {
            return '[]';
        }

        if (array_is_list($value)) {
            $items = array_map(fn (mixed $v): string => $this->formatDefault($v), $value);

            return '[' . implode(', ', $items) . ']';
        }

        $items = [];
        foreach ($value as $k => $v) {
            $items[] = "'" . addslashes((string) $k) . "' => " . $this->formatDefault($v);
        }

        return '[' . implode(', ', $items) . ']';
    }
}
