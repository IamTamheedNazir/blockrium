<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppIssueLogSubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('app_issue_logs', function ($table) {
            $table->increments('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('severity');
            $table->string('source');
            $table->string('action')->nullable();
            $table->string('endpoint')->nullable();
            $table->string('method')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_code')->nullable();
            $table->text('message')->nullable();
            $table->string('app_version')->nullable();
            $table->string('build_number')->nullable();
            $table->string('platform')->nullable();
            $table->string('device_model')->nullable();
            $table->string('os_version')->nullable();
            $table->unsignedInteger('request_duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_help_center_report_is_stored_with_sensitive_metadata_redacted(): void
    {
        $response = $this->postJson('/api/app-issue-log', [
            'severity' => 'warning',
            'source' => 'user_report',
            'action' => 'user_report',
            'endpoint' => 'manual/help-center',
            'method' => 'POST',
            'message' => 'Wallet: transfer screen freezes.',
            'platform' => 'android',
            'metadata' => [
                'category' => 'wallet',
                'access_token' => 'must-not-be-stored',
                'details' => 'Bearer fake.jwt-token',
            ],
        ]);

        $response->assertCreated()->assertJson(['success' => true]);

        $report = DB::table('app_issue_logs')->first();
        $this->assertNotNull($report);
        $this->assertSame('user_report', $report->source);
        $this->assertSame('Wallet: transfer screen freezes.', $report->message);
        $metadata = json_decode($report->metadata, true);
        $this->assertSame('[redacted]', $metadata['access_token']);
        $this->assertSame('Bearer [redacted]', $metadata['details']);
    }

    public function test_issue_report_cannot_assign_itself_to_another_user(): void
    {
        $response = $this->postJson('/api/app-issue-log', [
            'user_id' => 86788,
            'severity' => 'fatal',
            'source' => 'javascript',
            'action' => 'root_error_boundary',
            'message' => 'Startup render failed.',
        ]);

        $response->assertCreated();
        $this->assertNull(DB::table('app_issue_logs')->value('user_id'));
    }

    public function test_crash_stacks_are_preserved_with_a_safe_limit_and_secrets_redacted(): void
    {
        $stack = "TypeError: failure Bearer abc.def-secret\n" . str_repeat("frame\n", 2000);
        $componentStack = str_repeat(" at HomeOrg\n", 1200);

        $response = $this->postJson('/api/app-issue-log', [
            'severity' => 'fatal',
            'source' => 'react',
            'action' => 'root_error_boundary',
            'message' => 'undefined is not a function',
            'metadata' => [
                'stack' => $stack,
                'componentStack' => $componentStack,
                'details' => $stack,
            ],
        ]);

        $response->assertCreated();
        $metadata = json_decode(DB::table('app_issue_logs')->value('metadata'), true);

        $this->assertSame(8000, strlen($metadata['stack']));
        $this->assertSame(8000, strlen($metadata['componentStack']));
        $this->assertSame(1000, strlen($metadata['details']));
        $this->assertStringNotContainsString('abc.def-secret', $metadata['stack']);
    }
}
