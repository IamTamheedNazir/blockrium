<?php

namespace Tests\Unit;

use App\Services\GooglePlayBillingService;
use App\Services\IntegrationSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GooglePlaySettingsTest extends TestCase
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

        Schema::create('integration_settings', function ($table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_boolean_setting_uses_saved_value_and_missing_value_fallback(): void
    {
        $settings = new IntegrationSettingsService();

        $this->assertTrue($settings->boolean('not_configured', true));
        $this->assertFalse($settings->boolean('not_configured', false));

        $settings->put('boost_store_enabled', 'true', false);
        $settings->put('boost_checkout_enabled', '0', false);

        $this->assertTrue($settings->boolean('boost_store_enabled'));
        $this->assertFalse($settings->boolean('boost_checkout_enabled', true));
    }

    public function test_boost_mapping_reads_product_and_base_plan_for_plan_slug(): void
    {
        $settings = new IntegrationSettingsService();
        $settings->put('google_play_basic_monthly_product_id', 'brc_boost_basic', false);
        $settings->put('google_play_basic_monthly_base_plan_id', 'basic-monthly', false);
        $billing = new GooglePlayBillingService($settings);

        $this->assertSame([
            'product_id' => 'brc_boost_basic',
            'base_plan_id' => 'basic-monthly',
        ], $billing->boostProductMapping((object) ['slug' => 'basic-monthly']));
    }

    public function test_new_one_time_purchase_response_only_grants_entitlement_when_purchased(): void
    {
        $billing = new GooglePlayBillingService(new IntegrationSettingsService());
        $purchase = (object) ['product_id' => 'kyc_verification_fee'];
        $resource = [
            'productLineItem' => [['productId' => 'kyc_verification_fee']],
            'purchaseStateContext' => ['purchaseState' => 'PURCHASED'],
            'obfuscatedExternalAccountId' => 'account-binding',
            'orderId' => 'GPA.1234-5678-9012-34567',
            'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_PENDING',
        ];

        $normalized = $this->invokeBilling($billing, 'normalizeOneTimeProduct', [$purchase, $resource]);

        $this->assertSame('kyc_verification_fee', $normalized['product_id']);
        $this->assertSame('account-binding', $normalized['account_binding']);
        $this->assertSame('paid', $normalized['status']);
        $this->assertSame('active', $normalized['entitlement_status']);
        $this->assertTrue($normalized['acknowledgement_pending']);

        $resource['acknowledgementState'] = 'ACKNOWLEDGEMENT_STATE_UNSPECIFIED';
        $unspecifiedAcknowledgement = $this->invokeBilling($billing, 'normalizeOneTimeProduct', [$purchase, $resource]);
        $this->assertTrue($unspecifiedAcknowledgement['acknowledgement_pending']);

        unset($resource['acknowledgementState']);
        $missingAcknowledgement = $this->invokeBilling($billing, 'normalizeOneTimeProduct', [$purchase, $resource]);
        $this->assertTrue($missingAcknowledgement['acknowledgement_pending']);

        $resource['acknowledgementState'] = 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED';
        $acknowledged = $this->invokeBilling($billing, 'normalizeOneTimeProduct', [$purchase, $resource]);
        $this->assertFalse($acknowledged['acknowledgement_pending']);

        $resource['purchaseStateContext']['purchaseState'] = 'PENDING';
        $pending = $this->invokeBilling($billing, 'normalizeOneTimeProduct', [$purchase, $resource]);

        $this->assertSame('pending', $pending['status']);
        $this->assertSame('inactive', $pending['entitlement_status']);
    }

    public function test_cancelled_state_does_not_downgrade_a_previously_paid_membership(): void
    {
        Schema::create('google_play_purchases', function ($table) {
            $table->bigIncrements('id');
            $table->string('public_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('purpose');
            $table->string('product_id');
            $table->string('base_plan_id')->nullable();
            $table->string('account_binding');
            $table->string('purchase_token_hash')->nullable();
            $table->string('google_order_id')->nullable();
            $table->string('status');
            $table->string('entitlement_status');
            $table->decimal('store_amount', 12, 4)->nullable();
            $table->string('store_currency', 3)->nullable();
            $table->string('store_region', 2)->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('acknowledgement_state')->nullable();
            $table->unsignedBigInteger('verification_payment_id')->nullable();
            $table->unsignedBigInteger('user_boost_id')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->string('last_event_type')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('verification_payments', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('provider')->nullable();
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_payment_key')->nullable();
            $table->string('status');
            $table->decimal('amount', 10, 2)->default(10);
            $table->string('currency', 10)->default('USD');
            $table->text('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::create('verified_memberships', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('kyc_status');
            $table->string('payment_status');
            $table->string('payment_provider')->nullable();
            $table->string('payment_reference')->nullable();
            $table->decimal('payment_amount', 10, 2)->default(10);
            $table->string('payment_currency', 10)->default('USD');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        DB::table('verification_payments')->insert([
            'id' => 41,
            'user_id' => 7,
            'provider' => 'google_play',
            'status' => 'paid',
            'amount' => 10,
            'currency' => 'USD',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('verified_memberships')->insert([
            'user_id' => 7,
            'kyc_status' => 'approved',
            'payment_status' => 'paid',
            'verified_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('google_play_purchases')->insert([
            'id' => 12,
            'public_id' => 'paid-purchase',
            'user_id' => 7,
            'purpose' => 'kyc',
            'product_id' => 'blockrium_verified_membership',
            'account_binding' => 'account-binding',
            'status' => 'paid',
            'entitlement_status' => 'active',
            'verification_payment_id' => 41,
            'metadata' => json_encode(['order_amount_lookup_attempted' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $billing = new GooglePlayBillingService(new IntegrationSettingsService());
        $purchase = DB::table('google_play_purchases')->where('id', 12)->first();
        $resource = [
            'productLineItem' => [['productId' => 'blockrium_verified_membership']],
            'purchaseStateContext' => ['purchaseState' => 'CANCELLED'],
            'obfuscatedExternalAccountId' => 'account-binding',
            'orderId' => 'GPA.confirmed-order',
            'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED',
        ];

        foreach (['CANCELLED', 'PENDING'] as $storeState) {
            $resource['purchaseStateContext']['purchaseState'] = $storeState;
            $purchase = DB::table('google_play_purchases')->where('id', 12)->first();
            $result = $this->invokeBilling($billing, 'applyGooglePurchase', [$purchase, $resource]);

            $this->assertSame('paid', $result['status']);
            $this->assertSame('paid', DB::table('verification_payments')->where('id', 41)->value('status'));
            $this->assertSame('paid', DB::table('verified_memberships')->where('user_id', 7)->value('payment_status'));
            $this->assertNotNull(DB::table('verified_memberships')->where('user_id', 7)->value('verified_at'));
        }

        $persistedPurchase = DB::table('google_play_purchases')->where('id', 12)->first();
        $this->invokeBilling($billing, 'applyVoidedPurchase', [$persistedPurchase, [
            'orderId' => 'GPA.confirmed-order',
            'refundType' => '1',
        ]]);

        $this->assertSame('refunded', DB::table('google_play_purchases')->where('id', 12)->value('status'));
        $this->assertSame('refunded', DB::table('verification_payments')->where('id', 41)->value('status'));
        $this->assertSame('refunded', DB::table('verified_memberships')->where('user_id', 7)->value('payment_status'));
        $this->assertNull(DB::table('verified_memberships')->where('user_id', 7)->value('verified_at'));

        $resource['purchaseStateContext']['purchaseState'] = 'PURCHASED';
        $persistedPurchase = DB::table('google_play_purchases')->where('id', 12)->first();
        $result = $this->invokeBilling($billing, 'applyGooglePurchase', [$persistedPurchase, $resource]);

        $this->assertSame('refunded', $result['status']);
        $this->assertSame('refunded', DB::table('verification_payments')->where('id', 41)->value('status'));
        $this->assertSame('refunded', DB::table('verified_memberships')->where('user_id', 7)->value('payment_status'));
        $this->assertNull(DB::table('verified_memberships')->where('user_id', 7)->value('verified_at'));
    }

    public function test_google_play_kyc_purchase_intent_describes_membership_not_a_kyc_fee(): void
    {
        $billing = new GooglePlayBillingService(new IntegrationSettingsService());
        $payload = $this->invokeBilling($billing, 'intentPayload', [(object) [
            'purpose' => 'kyc',
            'verification_payment_id' => 21,
            'public_id' => 'purchase-intent',
            'product_id' => 'blockrium_verified_membership',
            'base_plan_id' => null,
            'account_binding' => 'account-binding',
            'status' => 'pending',
        ]]);

        $this->assertStringContainsString('membership', strtolower($payload['message']));
        $this->assertStringContainsString('for blockrium verified membership, not kyc', strtolower($payload['benefits']['payment_disclaimer']));
        $this->assertStringNotContainsString('pays for identity verification', strtolower($payload['benefits']['payment_disclaimer']));
    }

    public function test_subscription_response_grants_only_the_configured_product_and_base_plan(): void
    {
        $billing = new GooglePlayBillingService(new IntegrationSettingsService());
        $purchase = (object) [
            'purpose' => 'boost',
            'product_id' => 'brc_boost_basic',
            'base_plan_id' => 'basic-monthly',
            'account_binding' => 'account-binding',
        ];
        $resource = [
            'subscriptionState' => 'SUBSCRIPTION_STATE_ACTIVE',
            'externalAccountIdentifiers' => ['obfuscatedExternalAccountId' => 'account-binding'],
            'lineItems' => [[
                'productId' => 'brc_boost_basic',
                'expiryTime' => '2030-01-01T00:00:00Z',
                'latestSuccessfulOrderId' => 'GPA.1234-5678-9012-34567',
                'offerDetails' => ['basePlanId' => 'basic-monthly'],
            ]],
        ];

        $normalized = $this->invokeBilling($billing, 'normalizeSubscription', [$purchase, $resource]);
        $this->assertSame('paid', $normalized['status']);
        $this->assertSame('active', $normalized['entitlement_status']);
        $this->assertSame('basic-monthly', $normalized['base_plan_id']);
        $this->assertTrue($normalized['acknowledgement_pending']);

        $resource['acknowledgementState'] = 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED';
        $acknowledged = $this->invokeBilling($billing, 'normalizeSubscription', [$purchase, $resource]);
        $this->assertFalse($acknowledged['acknowledgement_pending']);

        $this->invokeBilling($billing, 'assertPurchaseMatchesIntent', [$purchase, $normalized]);

        $normalized['base_plan_id'] = 'elite-monthly';
        $this->expectException(HttpException::class);
        $this->invokeBilling($billing, 'assertPurchaseMatchesIntent', [$purchase, $normalized]);
    }

    public function test_subscription_without_a_future_expiry_never_grants_an_active_boost(): void
    {
        $billing = new GooglePlayBillingService(new IntegrationSettingsService());
        $purchase = (object) [
            'purpose' => 'boost',
            'product_id' => 'brc_boost_basic',
            'base_plan_id' => 'basic-monthly',
            'account_binding' => 'account-binding',
        ];
        $resource = [
            'subscriptionState' => 'SUBSCRIPTION_STATE_ACTIVE',
            'externalAccountIdentifiers' => ['obfuscatedExternalAccountId' => 'account-binding'],
            'lineItems' => [[
                'productId' => 'brc_boost_basic',
                'latestSuccessfulOrderId' => 'GPA.1234-5678-9012-34567',
                'offerDetails' => ['basePlanId' => 'basic-monthly'],
            ]],
        ];

        $withoutExpiry = $this->invokeBilling($billing, 'normalizeSubscription', [$purchase, $resource]);
        $this->assertSame('needs_review', $withoutExpiry['status']);
        $this->assertSame('inactive', $withoutExpiry['entitlement_status']);

        $resource['lineItems'][0]['expiryTime'] = now()->subMinute()->toIso8601String();
        $withExpiredDate = $this->invokeBilling($billing, 'normalizeSubscription', [$purchase, $resource]);
        $this->assertSame('needs_review', $withExpiredDate['status']);
        $this->assertSame('inactive', $withExpiredDate['entitlement_status']);
    }

    public function test_acknowledgement_retry_uses_purchase_time_through_the_play_acknowledgement_window(): void
    {
        Schema::create('google_play_purchases', function ($table) {
            $table->bigIncrements('id');
            $table->string('purpose');
            $table->string('product_id');
            $table->text('purchase_token_encrypted')->nullable();
            $table->string('status');
            $table->string('acknowledgement_state')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();
        });

        $old = now()->subHours(72);
        DB::table('google_play_purchases')->insert([
            [
                'purpose' => 'kyc',
                'product_id' => 'blockrium_verified',
                'purchase_token_encrypted' => Crypt::encryptString('recent-purchase-token'),
                'status' => 'paid',
                'acknowledgement_state' => 'ACKNOWLEDGEMENT_STATE_PENDING',
                'purchased_at' => now()->subHours(24),
                'created_at' => $old,
                'updated_at' => now(),
            ],
            [
                'purpose' => 'kyc',
                'product_id' => 'blockrium_verified',
                'purchase_token_encrypted' => Crypt::encryptString('expired-purchase-token'),
                'status' => 'paid',
                'acknowledgement_state' => 'ACKNOWLEDGEMENT_STATE_PENDING',
                'purchased_at' => $old,
                'created_at' => $old,
                'updated_at' => now(),
            ],
            [
                'purpose' => 'kyc',
                'product_id' => 'blockrium_verified',
                'purchase_token_encrypted' => Crypt::encryptString('last-day-purchase-token'),
                'status' => 'paid',
                'acknowledgement_state' => 'ACKNOWLEDGEMENT_STATE_PENDING',
                'purchased_at' => now()->subHours(69),
                'created_at' => $old,
                'updated_at' => now(),
            ],
            [
                'purpose' => 'kyc',
                'product_id' => 'blockrium_verified',
                'purchase_token_encrypted' => Crypt::encryptString('unspecified-acknowledgement-token'),
                'status' => 'paid',
                'acknowledgement_state' => 'ACKNOWLEDGEMENT_STATE_UNSPECIFIED',
                'purchased_at' => now()->subHours(20),
                'created_at' => $old,
                'updated_at' => now(),
            ],
            [
                'purpose' => 'kyc',
                'product_id' => 'blockrium_verified',
                'purchase_token_encrypted' => Crypt::encryptString('missing-acknowledgement-token'),
                'status' => 'paid',
                'acknowledgement_state' => null,
                'purchased_at' => now()->subHours(19),
                'created_at' => $old,
                'updated_at' => now(),
            ],
            [
                'purpose' => 'kyc',
                'product_id' => 'blockrium_verified',
                'purchase_token_encrypted' => Crypt::encryptString('acknowledged-token'),
                'status' => 'paid',
                'acknowledgement_state' => 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED',
                'purchased_at' => now()->subHours(18),
                'created_at' => $old,
                'updated_at' => now(),
            ],
        ]);

        $billing = new GooglePlayBillingService(new IntegrationSettingsService());
        $this->assertSame(4, $billing->pendingAcknowledgementCount());
        $stats = $billing->retryPendingAcknowledgements();

        $this->assertSame(['checked' => 4, 'acknowledged' => 0, 'pending' => 4], $stats);
    }

    public function test_admin_purchase_list_includes_store_cancellation_and_acknowledgement_details(): void
    {
        Schema::create('google_play_purchases', function ($table) {
            $table->bigIncrements('id');
            $table->uuid('public_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('purpose');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('product_id');
            $table->string('base_plan_id')->nullable();
            $table->string('google_order_id')->nullable();
            $table->string('status');
            $table->string('entitlement_status');
            $table->decimal('store_amount', 12, 4)->nullable();
            $table->string('store_currency', 3)->nullable();
            $table->string('store_region', 2)->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('acknowledgement_state')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->string('last_event_type')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('res_users', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
        });
        Schema::create('boost_plans', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
        });
        Schema::create('verified_memberships', function ($table) {
            $table->unsignedBigInteger('user_id');
            $table->string('kyc_status')->nullable();
            $table->string('payment_status')->nullable();
        });

        DB::table('res_users')->insert(['id' => 93, 'name' => 'Test User']);
        DB::table('google_play_purchases')->insert([
            'id' => 1,
            'user_id' => 93,
            'purpose' => 'kyc',
            'product_id' => 'blockrium_verified',
            'google_order_id' => 'GPA.test-order',
            'status' => 'refunded',
            'entitlement_status' => 'inactive',
            'acknowledgement_state' => 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED',
            'last_event_type' => 'voided_purchase',
            'metadata' => json_encode(['voided_refund_type' => '1']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $purchase = (new GooglePlayBillingService(new IntegrationSettingsService()))
            ->adminPurchases(['status' => 'refunded'])
            ->first();

        $this->assertSame('voided_purchase', $purchase->last_event_type);
        $this->assertSame('ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED', $purchase->acknowledgement_state);
        $this->assertSame(['voided_refund_type' => '1'], json_decode($purchase->metadata, true));
    }

    public function test_google_play_admin_view_labels_membership_separately_from_kyc_and_refunds(): void
    {
        $view = file_get_contents(resource_path('views/google-play-purchases/index.blade.php'));

        $this->assertStringContainsString('Blockrium Verified membership', $view);
        $this->assertStringNotContainsString('KYC verification', $view);
        $this->assertStringContainsString('Store-reported status. Check the order in Google Play Console', $view);
        $this->assertStringContainsString('this admin page does not issue refunds.', $view);
        $this->assertStringContainsString('Acknowledgement retry queue', $view);
        $this->assertStringContainsString('$stats[\'acknowledgement_pending\']', $view);
    }

    private function invokeBilling(GooglePlayBillingService $billing, string $methodName, array $arguments)
    {
        $method = new \ReflectionMethod($billing, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($billing, $arguments);
    }
}
