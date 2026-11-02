<?php

namespace Tests\Unit;

use App\Http\Controllers\AppIssueLogController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AppIssueLogSanitizationTest extends TestCase
{
    public function test_metadata_redacts_nested_secrets_and_bearer_tokens(): void
    {
        $controller = new AppIssueLogController();
        $method = (new ReflectionClass($controller))->getMethod('sanitizeMetadata');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'access_token' => 'top-secret',
            'nested' => [
                'RefreshToken' => 'refresh-secret',
                'stack' => 'Request failed with Bearer abc.def-123',
                'url' => '/profile?api_key=query-secret',
            ],
        ]);

        $this->assertSame('[redacted]', $result['access_token']);
        $this->assertSame('[redacted]', $result['nested']['RefreshToken']);
        $this->assertSame('Request failed with Bearer [redacted]', $result['nested']['stack']);
        $this->assertSame('/profile?api_key=[redacted]', $result['nested']['url']);
    }
}
