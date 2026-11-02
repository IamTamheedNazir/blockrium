<?php

namespace App\Support;

use ReflectionClass;
use ReflectionNamedType;

class ControllerDependencyScanner
{
    public function missingDependencies(string $class): array
    {
        $missing = [];
        $this->scan($class, [], $missing);

        return array_values(array_unique($missing));
    }

    private function scan(string $class, array $path, array &$missing): void
    {
        if (!class_exists($class) && !interface_exists($class)) {
            $missing[] = implode(' -> ', array_merge($path, [$class]));
            return;
        }

        if (in_array($class, $path, true)) {
            return;
        }

        $path[] = $class;
        $constructor = (new ReflectionClass($class))->getConstructor();
        if (!$constructor) {
            return;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $dependency = $type->getName();
            if (!class_exists($dependency) && !interface_exists($dependency)) {
                $missing[] = implode(' -> ', array_merge($path, [$dependency]));
                continue;
            }

            if (strpos($dependency, 'App\\') === 0) {
                $this->scan($dependency, $path, $missing);
            }
        }
    }
}
