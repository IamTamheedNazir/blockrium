<?php

namespace Tests\Unit;

use App\Jobs\SendPushNotificationJob;
use PHPUnit\Framework\TestCase;

class SendPushNotificationJobTest extends TestCase
{
    public function test_renames_firebase_reserved_message_type_key(): void
    {
        $payload = SendPushNotificationJob::prepareFcmData([
            'ping_id' => '42',
            'type' => 'REFERRAL_PING',
            'message_type' => 'mine_reminder',
        ]);

        $this->assertSame('42', $payload['ping_id']);
        $this->assertSame('REFERRAL_PING', $payload['type']);
        $this->assertSame('mine_reminder', $payload['app_message_type']);
        $this->assertArrayNotHasKey('message_type', $payload);
    }
}
