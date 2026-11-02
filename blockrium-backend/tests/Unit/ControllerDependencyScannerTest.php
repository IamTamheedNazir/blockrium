<?php

namespace Tests\Unit;

use App\Support\ControllerDependencyScanner;
use Tests\TestCase;

class ControllerDependencyScannerTest extends TestCase
{
    public function test_reports_a_missing_constructor_injection_with_its_dependency_chain(): void
    {
        $fixture = new class {
            public function __construct(\App\Services\MissingPreflightFixtureService $dependency = null)
            {
            }
        };

        $missing = (new ControllerDependencyScanner())->missingDependencies(get_class($fixture));

        $this->assertCount(1, $missing);
        $this->assertStringContainsString('MissingPreflightFixtureService', $missing[0]);
    }

    public function test_ignores_builtin_and_available_dependencies(): void
    {
        $fixture = new class {
            public function __construct(string $name = '', \stdClass $dependency = null)
            {
            }
        };

        $this->assertSame([], (new ControllerDependencyScanner())->missingDependencies(get_class($fixture)));
    }
}
