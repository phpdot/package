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
     * @param list<ScannedClass> $classes All scanned classes (grouped internally)
     * @return list<string> Generated file paths
     */
    public function generate(array $classes, string $configPath): array
    {
        $byPackage = [];

        foreach ($classes as $scanned) {
            $byPackage[$scanned->package][] = $scanned;
        }

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

            $siblings = $byPackage[$scanned->package] ?? [];
            $content = $this->generateFile($scanned, $siblings);
            file_put_contents($filePath, $content);
            $generated[] = $filePath;
        }

        return $generated;
    }

    /**
     * @param list<ScannedClass> $siblings All classes from the same package
     */
    private function generateFile(ScannedClass $scanned, array $siblings): string
    {
        $lines = [];
        $lines[] = "<?php\n";
        $lines[] = "\n/**";
        $lines[] = "\n * {$scanned->package}";
        $lines[] = "\n *";

        $hintLines = $this->generateHintBlock($siblings);

        if ($hintLines !== []) {
            foreach ($hintLines as $hint) {
                $lines[] = "\n * {$hint}";
            }

            $lines[] = "\n *";
        }

        $lines[] = "\n * Edit values below. Auto-generated once, never overwritten.";
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

    /**
     * @param list<ScannedClass> $siblings
     * @return list<string>
     */
    private function generateHintBlock(array $siblings): array
    {
        $lines = [];
        $lines[] = 'Services:';

        foreach ($siblings as $sibling) {
            $short = $this->shortName($sibling->class);
            $scope = strtolower($sibling->scope->name);
            $parts = $short . str_repeat(' ', max(1, 22 - strlen($short))) . $scope;

            if ($sibling->params !== []) {
                $paramNames = array_map($this->shortName(...), $sibling->params);
                $parts .= '     (' . implode(', ', $paramNames) . ')';
            }

            foreach ($sibling->binds as $interface) {
                $parts .= '  → binds ' . $this->shortName($interface);
            }

            $lines[] = '  ' . $parts;
        }

        $bindings = [];

        foreach ($siblings as $sibling) {
            foreach ($sibling->binds as $interface) {
                $bindings[] = [
                    'interface' => $interface,
                    'short' => $this->shortName($interface),
                ];
            }
        }

        if ($bindings !== []) {
            $lines[] = '';
            $lines[] = 'Override in container/services.php:';

            foreach ($bindings as $b) {
                $lines[] = "  {$b['short']}::class => singleton(fn (\$c) => new YourImpl(...))";
            }
        }

        $interfaceParams = [];

        foreach ($siblings as $sibling) {
            if ($sibling->configName !== null) {
                continue;
            }

            foreach ($sibling->params as $param) {
                if (interface_exists($param) && !in_array($param, $interfaceParams, true)) {
                    $interfaceParams[] = $param;
                }
            }
        }

        if ($interfaceParams !== []) {
            $lines[] = '';
            $lines[] = 'Contextual binding in container/bindings.php:';

            foreach ($interfaceParams as $iface) {
                $short = $this->shortName($iface);
                $lines[] = "  \$builder->when(Consumer::class)->needs({$short}::class)->provide(YourImpl::class);";
            }
        }

        return $lines;
    }

    private function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos !== false ? substr($fqcn, $pos + 1) : $fqcn;
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
