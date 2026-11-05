<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GooglePlayBillingService
{
    private const ANDROID_PUBLISHER_SCOPE = 'https://www.googleapis.com/auth/androidpublisher';
    private const API_BASE = 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/';

    private $settings;

    public function __construct(IntegrationSettingsService $settings)
    {
        $this->settings = $settings;
    }

    public function isConfigured(): bool
    {
        return $this->packageName() !== '' && $this->credentials() !== null;
    }

    public function retryPendingAcknowledgements(int $limit = 100): array
    {
        if (!Schema::hasTable('google_play_purchases')) {
            return ['checked' => 0, 'acknowledged' => 0, 'pending' => 0];
        }

        $purchases = $this->pendingAcknowledgementQuery()
            ->orderBy('created_at')
            ->limit(max(1, min(100, $limit)))
            ->get();

        $acknowledged = 0;
        $results = [];
        foreach ($purchases as $purchase) {
            $result = $this->acknowledgePurchase($purchase);
            $isAcknowledged = DB::table('google_play_purchases')
                ->where('id', $purchase->id)
                ->where('acknowledgement_state', 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED')
                ->exists();
            if ($isAcknowledged) {
                $acknowledged++;
                $result['outcome'] = 'acknowledged';
            }
            $results[] = ['purchase_id' => (int) $purchase->id] + $result;
        }

        return [
            'checked' => $purchases->count(),
            'acknowledged' => $acknowledged,
            'pending' => $purchases->count() - $acknowledged,
            'results' => $results,
        ];
    }

    public function pendingAcknowledgementCount(): int
    {
        if (!Schema::hasTable('google_play_purchases')) {
            return 0;
        }

        return $this->pendingAcknowledgementQuery()->count();
    }

    private function pendingAcknowledgementQuery()
    {
        return DB::table('google_play_purchases')
            ->where('status', 'paid')
            ->where(function ($query) {
                $query->whereNull('acknowledgement_state')
                    ->orWhere('acknowledgement_state', '!=', 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED');
            })
            ->whereNotNull('purchase_token_encrypted')
            ->where(function ($query) {
                // Keep retrying through most of Play's three-day acknowledgement window.
                $cutoff = now()->subHours(70);
                $query->where('purchased_at', '>=', $cutoff)
                    ->orWhere(function ($fallback) use ($cutoff) {
                        $fallback->whereNull('purchased_at')->where('created_at', '>=', $cutoff);
                    });
            });
    }

    public function boostProductMapping($plan): array
    {
        $key = $this->planSettingKey((string) $plan->slug);
        $productId = trim((string) $this->settings->get($key . '_product_id', ''));
        $basePlanId = trim((string) $this->settings->get($key . '_base_plan_id', ''));
        if ($productId === '' && ($plan->provider ?? null) === 'google_play') {
            $productId = trim((string) ($plan->provider_product_id ?? ''));
        }
        return ['product_id' => $productId, 'base_plan_id' => $basePlanId];
    }

    public function createIntent(int $userId, string $purpose, ?int $targetId = null): array
    {
        if (!Schema::hasTable('google_play_purchases')) {
            throw new HttpException(503, 'Google Play purchase records are not installed yet.');
        }
        if (!$this->isConfigured()) {
            throw new HttpException(503, 'Google Play billing is not configured yet.');
        }

        if ($purpose === 'kyc') {
            if (!$this->settings->boolean('google_play_kyc_enabled', false)) {
                throw new HttpException(403, 'Blockrium Verified membership activation is temporarily unavailable.');
            }
            $membership = DB::table('verified_memberships')->where('user_id', $userId)->first();
            if (!$membership || $membership->kyc_status !== 'approved') {
                throw new HttpException(403, 'Complete and pass identity verification before activating Blockrium Verified membership.');
            }
            if ($membership->payment_status === 'paid') {
                throw new HttpException(409, 'Blockrium Verified membership is already active for this account.');
            }
            $productId = trim((string) $this->settings->get('google_play_kyc_product_id', ''));
            $basePlanId = null;
            if ($productId === '') {
                throw new HttpException(503, 'The Google Play membership product is not configured.');
            }
        } elseif ($purpose === 'boost') {
            if (!$this->settings->boolean('boost_store_enabled', false)
                || !$this->settings->boolean('boost_checkout_enabled', false)) {
                throw new HttpException(403, 'Mining boost purchases are temporarily unavailable.');
            }
            $plan = DB::table('boost_plans')->where('id', $targetId)->where('status', 1)->first();
            if (!$plan) {
                throw new HttpException(404, 'Boost plan not found.');
            }
            $mapping = $this->boostProductMapping($plan);
            $productId = $mapping['product_id'];
            $basePlanId = $mapping['base_plan_id'];
            if ($productId === '' || $basePlanId === '') {
                throw new HttpException(503, 'The Google Play product and base plan for this boost are not configured.');
            }
            $activeBoost = DB::table('user_boosts')
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->where('payment_status', 'paid')
                ->where(function ($query) {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                })
                ->exists();
            if ($activeBoost) {
                throw new HttpException(409, 'An active mining boost is already linked to this account. Wait until it expires before purchasing another plan.');
            }
        } else {
            throw new HttpException(422, 'Unsupported Google Play purchase purpose.');
        }

        $existing = DB::table('google_play_purchases')
            ->where('user_id', $userId)
            ->where('purpose', $purpose)
            ->where('target_id', $targetId)
            ->where('product_id', $productId)
            ->whereIn('status', ['pending', 'needs_review'])
            ->where('created_at', '>=', now()->subHours(24))
            ->orderByDesc('id')
            ->first();
        if ($existing) {
            return $this->intentPayload($existing);
        }

        $publicId = (string) Str::uuid();
        $binding = $this->accountBinding($userId, $publicId);

        $purchase = DB::transaction(function () use ($userId, $purpose, $targetId, $productId, $basePlanId, $publicId, $binding) {
            $verificationPaymentId = null;
            $userBoostId = null;

            if ($purpose === 'kyc') {
                $membership = DB::table('verified_memberships')->where('user_id', $userId)->lockForUpdate()->first();
                if (!$membership || $membership->kyc_status !== 'approved' || $membership->payment_status === 'paid') {
                    throw new HttpException(409, 'Membership activation is not currently available for this account.');
                }

                $verificationPaymentId = DB::table('verification_payments')->insertGetId([
                    'user_id' => $userId,
                    'provider' => 'google_play',
                    'status' => 'pending',
                    'amount' => 0,
                    'currency' => 'XXX',
                    'metadata' => json_encode([
                        'purpose' => 'kyc',
                        'google_play_public_id' => $publicId,
                        'product_id' => $productId,
                        'store_amount_pending' => true,
                        'amount_note' => 'Google Play purchase verification response does not provide the charged price; use the order ID in Play Console.',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $plan = DB::table('boost_plans')->where('id', $targetId)->where('status', 1)->lockForUpdate()->first();
                if (!$plan) {
                    throw new HttpException(404, 'Boost plan is no longer available.');
                }
                $userBoostId = DB::table('user_boosts')->insertGetId([
                    'user_id' => $userId,
                    'boost_plan_id' => $plan->id,
                    'provider' => 'google_play',
                    'purchase_reference' => 'intent:' . $publicId,
                    'amount_paid' => 0,
                    'currency' => 'XXX',
                    'boost_percent' => $plan->boost_percent,
                    'starts_at' => null,
                    'ends_at' => null,
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'metadata' => json_encode([
                        'google_play_public_id' => $publicId,
                        'product_id' => $productId,
                        'base_plan_id' => $basePlanId,
                        'store_amount_pending' => true,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $id = DB::table('google_play_purchases')->insertGetId([
                'public_id' => $publicId,
                'user_id' => $userId,
                'purpose' => $purpose,
                'target_id' => $targetId,
                'product_id' => $productId,
                'base_plan_id' => $basePlanId,
                'account_binding' => $binding,
                'status' => 'pending',
                'entitlement_status' => 'inactive',
                'verification_payment_id' => $verificationPaymentId,
                'user_boost_id' => $userBoostId,
                'metadata' => json_encode(['source' => 'android_app']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($verificationPaymentId) {
                DB::table('verification_payments')->where('id', $verificationPaymentId)->update([
                    'metadata' => json_encode([
                        'purpose' => 'kyc',
                        'google_play_purchase_id' => $id,
                        'google_play_public_id' => $publicId,
                        'product_id' => $productId,
                        'store_amount_pending' => true,
                        'amount_note' => 'Google Play purchase verification response does not provide the charged price; use the order ID in Play Console.',
                    ]),
                    'updated_at' => now(),
                ]);
            }
            if ($userBoostId) {
                DB::table('user_boosts')->where('id', $userBoostId)->update([
                    'metadata' => json_encode([
                        'google_play_purchase_id' => $id,
                        'google_play_public_id' => $publicId,
                        'product_id' => $productId,
                        'base_plan_id' => $basePlanId,
                        'store_amount_pending' => true,
                    ]),
                    'updated_at' => now(),
                ]);
            }

            return DB::table('google_play_purchases')->where('id', $id)->first();
        });

        return $this->intentPayload($purchase);
    }

    public function verifyIntent(int $userId, string $publicId, string $purchaseToken): array
    {
        $purchaseToken = trim($purchaseToken);
        if (strlen($purchaseToken) < 20 || strlen($purchaseToken) > 4096) {
            throw new HttpException(422, 'Google Play returned an invalid purchase token.');
        }

        $purchase = DB::table('google_play_purchases')
            ->where('public_id', $publicId)
            ->where('user_id', $userId)
            ->first();
        if (!$purchase) {
            throw new HttpException(404, 'Purchase intent not found for this Blockrium account.');
        }

        $tokenHash = hash('sha256', $purchaseToken);
        $duplicate = DB::table('google_play_purchases')->where('purchase_token_hash', $tokenHash)->first();
        if ($duplicate && (int) $duplicate->id !== (int) $purchase->id) {
            throw new HttpException(409, 'This Google Play purchase is already linked to another purchase record.');
        }

        $googlePurchase = $this->fetchPurchase($purchase, $purchaseToken);
        $normalized = $purchase->purpose === 'boost'
            ? $this->normalizeSubscription($purchase, $googlePurchase)
            : $this->normalizeOneTimeProduct($purchase, $googlePurchase);
        $this->assertPurchaseMatchesIntent($purchase, $normalized);

        DB::transaction(function () use ($purchase, $purchaseToken, $tokenHash) {
            $locked = DB::table('google_play_purchases')->where('id', $purchase->id)->lockForUpdate()->first();
            if (!$locked || ($locked->purchase_token_hash && !hash_equals($locked->purchase_token_hash, $tokenHash))) {
                throw new HttpException(409, 'This purchase intent is already linked to a different Google Play transaction.');
            }
            $duplicate = DB::table('google_play_purchases')->where('purchase_token_hash', $tokenHash)->where('id', '!=', $purchase->id)->exists();
            if ($duplicate) {
                throw new HttpException(409, 'This Google Play purchase is already linked to another Blockrium account.');
            }
            DB::table('google_play_purchases')->where('id', $purchase->id)->update([
                'purchase_token_hash' => $tokenHash,
                'purchase_token_encrypted' => Crypt::encryptString($purchaseToken),
                'updated_at' => now(),
            ]);
        });
        $purchase = DB::table('google_play_purchases')->where('id', $purchase->id)->first();
        return $this->applyGooglePurchase($purchase, $googlePurchase);
    }

    public function verifyRtdnIdentity(string $idToken)
    {
        $audience = trim((string) $this->settings->get('google_play_rtdn_audience', ''));
        $expectedEmail = trim((string) $this->settings->get('google_play_rtdn_service_account_email', ''));
        if ($audience === '' || $expectedEmail === '') {
            return false;
        }

        try {
            $client = new \Google_Client();
            $claims = $client->verifyIdToken($idToken, $audience);
            return $claims
                && !empty($claims['email_verified'])
                && isset($claims['email'])
                && hash_equals($expectedEmail, (string) $claims['email'])
                && in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
                ? $claims
                : false;
        } catch (\Throwable $exception) {
            Log::warning('Google Play RTDN identity verification failed.', ['exception' => get_class($exception)]);
            return false;
        }
    }

    public function processRtdn(array $message): array
    {
        if (!Schema::hasTable('google_play_rtdn_events')) {
            throw new HttpException(503, 'Google Play notification storage is not installed.');
        }

        $messageId = (string) ($message['messageId'] ?? $message['message_id'] ?? '');
        $encoded = (string) ($message['data'] ?? '');
        if ($messageId === '' || strlen($messageId) > 190 || $encoded === '') {
            throw new HttpException(400, 'Invalid Google Play notification envelope.');
        }

        $decoded = base64_decode($encoded, true);
        $payload = $decoded === false ? null : json_decode($decoded, true);
        if (!is_array($payload)) {
            throw new HttpException(400, 'Invalid Google Play notification payload.');
        }
        if (!empty($payload['testNotification'])) {
            $this->recordRtdnEvent($messageId, 'test', null, 'processed');
            return ['duplicate' => false, 'test' => true];
        }

        $subscription = $payload['subscriptionNotification'] ?? null;
        $oneTime = $payload['oneTimeProductNotification'] ?? null;
        $voided = $payload['voidedPurchaseNotification'] ?? null;
        $notification = is_array($subscription) ? $subscription : (is_array($oneTime) ? $oneTime : (is_array($voided) ? $voided : null));
        if (!$notification || empty($notification['purchaseToken'])) {
            $this->recordRtdnEvent($messageId, 'unsupported', null, 'ignored');
            return ['duplicate' => false, 'ignored' => true];
        }

        $token = (string) $notification['purchaseToken'];
        $tokenHash = hash('sha256', $token);
        $eventType = is_array($subscription)
            ? 'subscription:' . (string) ($subscription['notificationType'] ?? 'unknown')
            : (is_array($oneTime)
                ? 'one_time:' . (string) ($oneTime['notificationType'] ?? 'unknown')
                : 'voided:' . (string) ($voided['refundType'] ?? 'unknown'));
        $existingEvent = DB::table('google_play_rtdn_events')->where('message_id', $messageId)->first();
        if ($existingEvent && $existingEvent->status === 'processed') {
            return ['duplicate' => true];
        }
        $this->recordRtdnEvent($messageId, $eventType, $tokenHash, 'received');

        $purchase = DB::table('google_play_purchases')->where('purchase_token_hash', $tokenHash)->first();
        if (!$purchase) {
            $this->recordRtdnEvent($messageId, $eventType, $tokenHash, 'unmatched');
            return ['duplicate' => false, 'unmatched' => true];
        }

        try {
            if (is_array($voided) && !empty($voided['orderId'])
                && hash_equals((string) ($purchase->google_order_id ?? ''), (string) $voided['orderId'])) {
                $this->applyVoidedPurchase($purchase, $voided);
            } else {
                $storedToken = Crypt::decryptString((string) $purchase->purchase_token_encrypted);
                $currentPurchase = $this->fetchPurchase($purchase, $storedToken);
                $this->applyGooglePurchase($purchase, $currentPurchase);
            }
            $this->linkRtdnEvent($messageId, $purchase);
            $this->recordRtdnEvent($messageId, $eventType, $tokenHash, 'processed');
            return ['duplicate' => false, 'processed' => true];
        } catch (\Throwable $exception) {
            $this->recordRtdnEvent($messageId, $eventType, $tokenHash, 'failed', 'purchase_reconciliation_failed');
            Log::warning('Google Play notification reconciliation failed.', [
                'message_id' => $messageId,
                'purchase_id' => (int) $purchase->id,
                'exception' => get_class($exception),
            ]);
            throw $exception;
        }
    }

    public function adminPurchases(array $filters)
    {
        $query = DB::table('google_play_purchases as p')
            ->leftJoin('res_users as u', 'u.id', '=', 'p.user_id')
            ->leftJoin('boost_plans as bp', function ($join) {
                $join->on('bp.id', '=', 'p.target_id')->where('p.purpose', '=', 'boost');
            })
            ->leftJoin('verified_memberships as vm', 'vm.user_id', '=', 'p.user_id')
            ->select(
                'p.id', 'p.public_id', 'p.user_id', 'p.purpose', 'p.product_id', 'p.base_plan_id',
                'p.google_order_id', 'p.status', 'p.entitlement_status', 'p.store_amount', 'p.store_currency',
                'p.store_region', 'p.purchased_at', 'p.expires_at', 'p.acknowledgement_state',
                'p.last_verified_at', 'p.last_event_type', 'p.metadata', 'p.created_at', 'u.name', 'u.username', 'u.email',
                'bp.name as boost_plan_name', 'vm.kyc_status', 'vm.payment_status as kyc_payment_status'
            )
            ->orderByDesc('p.id');

        if (!empty($filters['purpose'])) {
            $query->where('p.purpose', $filters['purpose']);
        }
        if (!empty($filters['status'])) {
            $query->where('p.status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($where) use ($search) {
                $where->where('u.name', 'like', '%' . $search . '%')
                    ->orWhere('u.username', 'like', '%' . $search . '%')
                    ->orWhere('u.email', 'like', '%' . $search . '%')
                    ->orWhere('p.google_order_id', 'like', '%' . $search . '%')
                    ->orWhere('p.product_id', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate(50)->withQueryString();
    }

    public function rtdnEvents()
    {
        return DB::table('google_play_rtdn_events as e')
            ->leftJoin('google_play_purchases as p', 'p.id', '=', 'e.purchase_id')
            ->leftJoin('res_users as u', 'u.id', '=', 'p.user_id')
            ->select('e.*', 'u.name', 'u.username', 'u.email', 'p.user_id')
            ->orderByDesc('e.id')
            ->limit(30)
            ->get();
    }

    private function fetchPurchase($purchase, string $purchaseToken): array
    {
        $accessToken = $this->accessToken();
        $package = rawurlencode($this->packageName());
        $token = rawurlencode($purchaseToken);
        if ($purchase->purpose === 'boost') {
            $url = self::API_BASE . $package . '/purchases/subscriptionsv2/tokens/' . $token;
        } else {
            $url = self::API_BASE . $package . '/purchases/productsv2/tokens/' . $token;
        }

        $response = Http::withToken($accessToken)->acceptJson()->timeout(15)->get($url);
        if (!$response->successful()) {
            Log::warning('Google Play purchase lookup failed.', [
                'purchase_id' => (int) $purchase->id,
                'http_status' => $response->status(),
            ]);
            if ($response->status() === 404 || $response->status() === 400) {
                throw new HttpException(422, 'Google Play could not verify this purchase for the configured app.');
            }
            throw new HttpException(503, 'Google Play verification is temporarily unavailable. Please retry.');
        }

        return (array) $response->json();
    }

    private function applyGooglePurchase($purchase, array $resource): array
    {
        $normalized = $purchase->purpose === 'boost'
            ? $this->normalizeSubscription($purchase, $resource)
            : $this->normalizeOneTimeProduct($purchase, $resource);

        if ($normalized['product_id'] !== (string) $purchase->product_id) {
            $this->markNeedsReview($purchase, 'product_mismatch');
            throw new HttpException(409, 'The Google Play product does not match this purchase intent.');
        }
        if ($purchase->purpose === 'boost' && $normalized['base_plan_id'] !== (string) $purchase->base_plan_id) {
            $this->markNeedsReview($purchase, 'base_plan_mismatch');
            throw new HttpException(409, 'The Google Play base plan does not match this purchase intent.');
        }
        if (($normalized['account_binding'] !== '' && !hash_equals((string) $purchase->account_binding, $normalized['account_binding']))
            || ($normalized['status'] === 'paid' && $normalized['account_binding'] === '')) {
            $this->markNeedsReview($purchase, 'account_binding_mismatch');
            throw new HttpException(409, 'This purchase could not be matched to the Blockrium account that started it.');
        }

        $previousMetadata = json_decode((string) ($purchase->metadata ?? ''), true) ?: [];
        $orderLookupAttempted = !empty($previousMetadata['order_amount_lookup_attempted']);
        if ($normalized['amount'] === null && !empty($normalized['order_id'])
            && (!$orderLookupAttempted || ($normalized['status'] === 'paid' && $purchase->status !== 'paid'))) {
            $normalized = $this->enrichOrderAmount($purchase, $normalized);
        } else {
            $normalized['metadata']['order_amount_lookup_attempted'] = $orderLookupAttempted;
        }

        DB::transaction(function () use ($purchase, &$normalized) {
            $locked = DB::table('google_play_purchases')->where('id', $purchase->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new HttpException(404, 'Purchase record no longer exists.');
            }

            if ($locked->purpose === 'kyc'
                && in_array($locked->status, ['refunded', 'revoked', 'needs_review'], true)
                && $normalized['status'] === 'paid') {
                $normalized['status'] = $locked->status;
                $normalized['entitlement_status'] = 'inactive';
                $normalized['metadata']['ignored_reactivation_after_terminal_purchase_state'] = $locked->status;
            }

            if ($locked->purpose === 'kyc'
                && $locked->status === 'paid'
                && in_array($normalized['status'], ['pending', 'cancelled'], true)) {
                $normalized['status'] = 'paid';
                $normalized['entitlement_status'] = $locked->entitlement_status;
                $normalized['metadata']['ignored_non_authoritative_cancel_after_paid'] = true;
            }

            DB::table('google_play_purchases')->where('id', $locked->id)->update([
                'google_order_id' => $normalized['order_id'] ?: $locked->google_order_id,
                'status' => $normalized['status'],
                'entitlement_status' => $normalized['entitlement_status'],
                'store_amount' => $normalized['amount'] !== null ? $normalized['amount'] : $locked->store_amount,
                'store_currency' => $normalized['currency'] ?: $locked->store_currency,
                'store_region' => $normalized['region'],
                'purchased_at' => $normalized['purchased_at'],
                'expires_at' => $normalized['expires_at'],
                'acknowledgement_state' => $normalized['acknowledgement_state'],
                'last_verified_at' => now(),
                'metadata' => json_encode($normalized['metadata']),
                'updated_at' => now(),
            ]);

            if ($locked->purpose === 'kyc' && $locked->verification_payment_id) {
                $payment = DB::table('verification_payments')->where('id', $locked->verification_payment_id)->lockForUpdate()->first();
                if ($payment) {
                    $paymentStatus = $normalized['status'] === 'paid' ? 'paid' : $normalized['status'];
                    $paymentUpdates = [
                        'provider' => 'google_play',
                        'provider_payment_id' => $normalized['order_id'],
                        'provider_payment_key' => $locked->purchase_token_hash,
                        'status' => $paymentStatus,
                        'paid_at' => $normalized['status'] === 'paid' ? ($normalized['purchased_at'] ?: now()) : $payment->paid_at,
                        'metadata' => json_encode([
                            'purpose' => 'kyc',
                            'google_play_purchase_id' => (int) $locked->id,
                            'product_id' => $locked->product_id,
                            'purchase_status' => $normalized['status'],
                            'store_amount_pending' => $normalized['amount'] === null,
                            'amount_note' => $normalized['amount'] === null
                                ? 'Use the Google Play order ID in Play Console to inspect the charged price.'
                                : null,
                        ]),
                        'updated_at' => now(),
                    ];
                    if ($normalized['amount'] !== null) {
                        $paymentUpdates['amount'] = $normalized['amount'];
                    }
                    if ($normalized['currency']) {
                        $paymentUpdates['currency'] = $normalized['currency'];
                    }
                    DB::table('verification_payments')->where('id', $payment->id)->update($paymentUpdates);
                }

                $membership = DB::table('verified_memberships')->where('user_id', $locked->user_id)->lockForUpdate()->first();
                if ($membership && $normalized['status'] === 'paid') {
                    DB::table('verified_memberships')->where('id', $membership->id)->update([
                        'payment_status' => 'paid',
                        'payment_provider' => 'google_play',
                        'payment_reference' => $normalized['order_id'] ?: ('gplay:' . substr((string) $locked->purchase_token_hash, 0, 32)),
                        'payment_amount' => $normalized['amount'] !== null ? $normalized['amount'] : 0,
                        'payment_currency' => $normalized['currency'] ?: 'XXX',
                        'verified_at' => $membership->kyc_status === 'approved' && !$membership->suspended_at
                            ? ($membership->verified_at ?: now())
                            : null,
                        'updated_at' => now(),
                    ]);
                } elseif ($membership && in_array($normalized['status'], ['cancelled', 'failed', 'refunded', 'revoked'], true)) {
                    DB::table('verified_memberships')->where('id', $membership->id)->update([
                        'payment_status' => $normalized['status'],
                        'verified_at' => null,
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($locked->purpose === 'boost' && $locked->user_boost_id) {
                $isEntitled = $normalized['entitlement_status'] === 'active';
                $boostStatus = $isEntitled ? 'active' : ($normalized['status'] === 'paid' ? 'expired' : $normalized['status']);
                DB::table('user_boosts')->where('id', $locked->user_boost_id)->update([
                    'provider' => 'google_play',
                    'purchase_reference' => $normalized['order_id'] ?: ('gplay:' . substr((string) $locked->purchase_token_hash, 0, 32)),
                    'amount_paid' => $normalized['amount'] !== null ? $normalized['amount'] : 0,
                    'currency' => $normalized['currency'] ?: 'XXX',
                    'status' => $boostStatus,
                    'payment_status' => $normalized['status'] === 'paid' ? 'paid' : $normalized['status'],
                    'starts_at' => $normalized['status'] === 'paid' ? ($normalized['purchased_at'] ?: now()) : null,
                    'ends_at' => $normalized['expires_at'],
                    'metadata' => json_encode([
                        'google_play_purchase_id' => (int) $locked->id,
                        'product_id' => $locked->product_id,
                        'base_plan_id' => $locked->base_plan_id,
                        'subscription_state' => $normalized['metadata']['subscription_state'] ?? null,
                        'auto_renew_enabled' => $normalized['metadata']['auto_renew_enabled'] ?? null,
                        'store_amount_pending' => $normalized['amount'] === null,
                        'google_order_id' => $normalized['order_id'],
                    ]),
                    'updated_at' => now(),
                ]);
            }
        });

        $updated = DB::table('google_play_purchases')->where('id', $purchase->id)->first();
        if ($normalized['status'] === 'paid' && $normalized['acknowledgement_pending']) {
            $this->acknowledgePurchase($updated);
        }
        if ($updated->purchase_token_hash) {
            DB::table('google_play_rtdn_events')->where('purchase_token_hash', $updated->purchase_token_hash)->where('status', 'unmatched')->update([
                'status' => 'processed',
                'purchase_id' => (int) $updated->id,
                'google_order_id' => $updated->google_order_id,
                'store_amount' => $updated->store_amount,
                'store_currency' => $updated->store_currency,
                'processed_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'purchase_id' => $updated->public_id,
            'purpose' => $updated->purpose,
            'status' => $updated->status,
            'entitlement_status' => $updated->entitlement_status,
            'google_order_id' => $updated->google_order_id,
            'expires_at' => $updated->expires_at,
            'payment_status' => $updated->purpose === 'kyc' ? $updated->status : null,
        ];
    }

    private function assertPurchaseMatchesIntent($purchase, array $normalized): void
    {
        if ($normalized['product_id'] !== (string) $purchase->product_id) {
            throw new HttpException(409, 'The Google Play product does not match this purchase intent.');
        }
        if ($purchase->purpose === 'boost' && $normalized['base_plan_id'] !== (string) $purchase->base_plan_id) {
            throw new HttpException(409, 'The Google Play base plan does not match this purchase intent.');
        }
        if ($normalized['account_binding'] === ''
            || !hash_equals((string) $purchase->account_binding, $normalized['account_binding'])) {
            throw new HttpException(409, 'This purchase could not be matched to the Blockrium account that started it.');
        }
    }

    private function applyVoidedPurchase($purchase, array $notification): void
    {
        DB::transaction(function () use ($purchase, $notification) {
            $locked = DB::table('google_play_purchases')->where('id', $purchase->id)->lockForUpdate()->first();
            if (!$locked || !hash_equals((string) ($locked->google_order_id ?? ''), (string) ($notification['orderId'] ?? ''))) {
                return;
            }

            $metadata = json_decode((string) ($locked->metadata ?? ''), true) ?: [];
            $metadata['voided_order_id'] = (string) ($notification['orderId'] ?? '');
            $metadata['voided_refund_type'] = $notification['refundType'] ?? null;
            $metadata['voided_received_at'] = now()->toIso8601String();
            $reviewRequired = isset($notification['refundType'])
                && !in_array((string) $notification['refundType'], ['1', 'REFUND_TYPE_FULL_REFUND'], true);

            DB::table('google_play_purchases')->where('id', $locked->id)->update([
                'status' => $reviewRequired ? 'needs_review' : 'refunded',
                'entitlement_status' => 'inactive',
                'last_event_type' => 'voided_purchase',
                'metadata' => json_encode($metadata),
                'updated_at' => now(),
            ]);

            if (!$reviewRequired && $locked->verification_payment_id) {
                DB::table('verification_payments')->where('id', $locked->verification_payment_id)->update([
                    'status' => 'refunded',
                    'metadata' => json_encode(array_merge($metadata, ['purchase_status' => 'refunded'])),
                    'updated_at' => now(),
                ]);
            }

            // Revoke the KYC membership only when this was a full refund and no other
            // server-recorded KYC purchase still grants an active entitlement.
            if (!$reviewRequired && $locked->purpose === 'kyc') {
                $hasAnotherActiveKycPurchase = DB::table('google_play_purchases')
                    ->where('user_id', $locked->user_id)
                    ->where('purpose', 'kyc')
                    ->where('id', '!=', $locked->id)
                    ->where('status', 'paid')
                    ->where('entitlement_status', 'active')
                    ->exists();
                if (!$hasAnotherActiveKycPurchase) {
                    DB::table('verified_memberships')->where('user_id', $locked->user_id)->update([
                        'payment_status' => 'refunded',
                        'verified_at' => null,
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($locked->user_boost_id) {
                DB::table('user_boosts')->where('id', $locked->user_boost_id)->update([
                    'status' => $reviewRequired ? 'needs_review' : 'refunded',
                    'payment_status' => 'refunded',
                    'updated_at' => now(),
                ]);
            }
        });
    }

    private function normalizeOneTimeProduct($purchase, array $resource): array
    {
        $line = (array) (($resource['productLineItem'][0] ?? []));
        $context = (array) ($resource['purchaseStateContext'] ?? []);
        $state = strtoupper((string) ($context['purchaseState'] ?? ''));
        $status = $state === 'PURCHASED' ? 'paid' : ($state === 'PENDING' ? 'pending' : ($state === 'CANCELLED' ? 'cancelled' : 'needs_review'));
        return [
            'product_id' => (string) ($line['productId'] ?? ''),
            'base_plan_id' => null,
            'account_binding' => (string) ($resource['externalAccountIdentifiers']['obfuscatedExternalAccountId']
                ?? $resource['obfuscatedExternalAccountId']
                ?? ''),
            'status' => $status,
            'entitlement_status' => $status === 'paid' ? 'active' : 'inactive',
            'order_id' => $resource['orderId'] ?? null,
            'amount' => null,
            'currency' => null,
            'region' => $resource['regionCode'] ?? null,
            'purchased_at' => $this->sqlDate($resource['purchaseCompletionTime'] ?? null),
            'expires_at' => null,
            'acknowledgement_state' => $resource['acknowledgementState'] ?? null,
            'acknowledgement_pending' => $status === 'paid'
                && ($resource['acknowledgementState'] ?? '') !== 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED',
            'metadata' => ['purchase_state' => $state],
        ];
    }

    private function normalizeSubscription($purchase, array $resource): array
    {
        $line = [];
        foreach ((array) ($resource['lineItems'] ?? []) as $candidate) {
            if (($candidate['productId'] ?? null) === (string) $purchase->product_id) {
                $line = (array) $candidate;
                break;
            }
        }
        $offer = (array) ($line['offerDetails'] ?? []);
        $state = strtoupper((string) ($resource['subscriptionState'] ?? ''));
        $expiresAt = $line['expiryTime'] ?? null;
        $futureExpiry = $expiresAt && strtotime((string) $expiresAt) > time();
        $activeState = $futureExpiry && in_array($state, [
            'SUBSCRIPTION_STATE_ACTIVE',
            'SUBSCRIPTION_STATE_IN_GRACE_PERIOD',
            'SUBSCRIPTION_STATE_CANCELED',
        ], true);
        $status = $activeState ? 'paid' : ($state === 'SUBSCRIPTION_STATE_PENDING' ? 'pending' : ($state === 'SUBSCRIPTION_STATE_EXPIRED' ? 'expired' : ($state === 'SUBSCRIPTION_STATE_CANCELED' ? 'cancelled' : ($state === 'SUBSCRIPTION_STATE_ON_HOLD' ? 'on_hold' : 'needs_review'))));
        $purchasedAt = $resource['startTime'] ?? null;
        return [
            'product_id' => (string) ($line['productId'] ?? ''),
            'base_plan_id' => (string) ($offer['basePlanId'] ?? ''),
            'account_binding' => (string) (($resource['externalAccountIdentifiers']['obfuscatedExternalAccountId'] ?? '')),
            'status' => $status,
            'entitlement_status' => $activeState ? 'active' : 'inactive',
            'order_id' => $line['latestSuccessfulOrderId'] ?? null,
            'amount' => null,
            'currency' => null,
            'region' => $resource['regionCode'] ?? null,
            'purchased_at' => $this->sqlDate($purchasedAt),
            'expires_at' => $this->sqlDate($expiresAt),
            'acknowledgement_state' => $resource['acknowledgementState'] ?? null,
            'acknowledgement_pending' => $status === 'paid'
                && ($resource['acknowledgementState'] ?? '') !== 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED',
            'metadata' => [
                'subscription_state' => $state,
                'auto_renew_enabled' => $line['autoRenewingPlan']['autoRenewEnabled'] ?? null,
            ],
        ];
    }

    private function enrichOrderAmount($purchase, array $normalized): array
    {
        $normalized['metadata']['order_amount_lookup_attempted'] = true;
        try {
            $url = self::API_BASE . rawurlencode($this->packageName()) . '/orders/' . rawurlencode((string) $normalized['order_id']);
            $response = Http::withToken($this->accessToken())->acceptJson()->timeout(5)->get($url);
            if (!$response->successful()) {
                Log::info('Google Play order amount is not available for this purchase.', [
                    'purchase_id' => (int) $purchase->id,
                    'http_status' => $response->status(),
                ]);
                return $normalized;
            }

            $order = (array) $response->json();
            foreach ((array) ($order['lineItems'] ?? []) as $lineItem) {
                if (($lineItem['productId'] ?? null) !== (string) $purchase->product_id) {
                    continue;
                }
                $total = (array) ($lineItem['total'] ?? []);
                if (!array_key_exists('units', $total)) {
                    continue;
                }
                $amount = (float) $total['units'] + ((float) ($total['nanos'] ?? 0) / 1000000000);
                if ($amount > 0 && !empty($total['currencyCode'])) {
                    $normalized['amount'] = round($amount, 4);
                    $normalized['currency'] = strtoupper((string) $total['currencyCode']);
                }
                break;
            }
        } catch (\Throwable $exception) {
            Log::info('Google Play order amount lookup was skipped.', [
                'purchase_id' => (int) $purchase->id,
                'exception' => get_class($exception),
            ]);
        }

        return $normalized;
    }

    private function acknowledgePurchase($purchase): array
    {
        try {
            $token = Crypt::decryptString((string) $purchase->purchase_token_encrypted);
            $package = rawurlencode($this->packageName());
            $token = rawurlencode($token);
            if ($purchase->purpose === 'boost') {
                $product = rawurlencode((string) $purchase->product_id);
                $path = self::API_BASE . $package . '/purchases/subscriptions/' . $product . '/tokens/' . $token . ':acknowledge';
            } else {
                $product = rawurlencode((string) $purchase->product_id);
                $path = self::API_BASE . $package . '/purchases/products/' . $product . '/tokens/' . $token . ':acknowledge';
            }
            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->withBody('{}', 'application/json')
                ->timeout(15)
                ->send('POST', $path);
            if ($response->successful() || $response->status() === 409) {
                DB::table('google_play_purchases')->where('id', $purchase->id)->update([
                    'acknowledgement_state' => 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED',
                    'updated_at' => now(),
                ]);
                return ['outcome' => 'acknowledged', 'http_status' => $response->status()];
            }

            Log::warning('Google Play purchase acknowledgement failed.', ['purchase_id' => (int) $purchase->id, 'http_status' => $response->status()]);
            return ['outcome' => 'http_error', 'http_status' => $response->status()];
        } catch (\Throwable $exception) {
            $location = $exception->getFile();
            Log::warning('Google Play purchase acknowledgement could not be completed.', [
                'purchase_id' => (int) $purchase->id,
                'exception' => get_class($exception),
                'source_file' => basename($location),
                'source_line' => $exception->getLine(),
                'http_status' => $exception instanceof HttpException ? $exception->getStatusCode() : null,
            ]);
            return [
                'outcome' => 'exception',
                'exception' => get_class($exception),
                'source' => basename($location) . ':' . $exception->getLine(),
                'http_status' => $exception instanceof HttpException ? $exception->getStatusCode() : null,
            ];
        }
    }

    private function accessToken(): string
    {
        $credentials = $this->credentials();
        if (!$credentials) {
            throw new HttpException(503, 'Google Play service credentials are not configured.');
        }
        $fingerprint = hash('sha256', $credentials['client_email'] . '|' . $credentials['private_key']);
        $cacheKey = 'google_play_publisher_token_' . $fingerprint;
        $cachedToken = Cache::get($cacheKey);
        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        try {
            $client = new \Google_Client();
            $client->setAuthConfig($credentials);
            $client->addScope(self::ANDROID_PUBLISHER_SCOPE);
            $token = $client->fetchAccessTokenWithAssertion();
            if (empty($token['access_token'])) {
                throw new \RuntimeException('Google did not issue an access token.');
            }
            $accessToken = (string) $token['access_token'];
            $ttl = max(60, min(3300, (int) ($token['expires_in'] ?? 3600) - 120));
            Cache::put($cacheKey, $accessToken, now()->addSeconds($ttl));
            return $accessToken;
        } catch (\Throwable $exception) {
            Log::error('Google Play service-account authentication failed.', ['exception' => get_class($exception)]);
            throw new HttpException(503, 'Google Play authentication is not available. Check service-account setup.');
        }
    }

    private function credentials(): ?array
    {
        $raw = $this->settings->get('google_play_service_account_json', '');
        $credentials = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            return null;
        }
        return $credentials;
    }

    private function packageName(): string
    {
        return trim((string) $this->settings->get('google_play_package_name', 'com.blockrium'));
    }

    private function accountBinding(int $userId, string $publicId): string
    {
        return hash_hmac('sha256', $userId . '|' . $publicId, (string) config('app.key'));
    }

    private function sqlDate($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateTimeString();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function planSettingKey(string $slug): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '_', $slug));
        return 'google_play_' . trim($slug, '_');
    }

    private function intentPayload($purchase): array
    {
        $benefits = [];
        if ($purchase->purpose === 'kyc') {
            $benefits = [
                'verified_badge' => 'A verified Blockrium member badge after Didit approval and payment confirmation.',
                'verified_features' => 'Access to eligible verified-member features, including a BRC username and transfers subject to limits and risk checks.',
                'payment_disclaimer' => 'This one-time payment is for Blockrium Verified membership, not KYC. Identity review is separate and must be approved before membership benefits activate.',
            ];
        } else {
            $plan = DB::table('boost_plans')->where('id', $purchase->target_id)->first();
            if ($plan) {
                $benefits = [
                    'boost_percent' => (float) $plan->boost_percent,
                    'duration_days' => (int) $plan->duration_days,
                    'summary' => 'Adds ' . rtrim(rtrim(number_format((float) $plan->boost_percent, 2, '.', ''), '0'), '.') . '% to the configured mining rate while the Google Play subscription is active.',
                    'earnings_disclaimer' => 'A rate increase does not guarantee a token amount, token value, exchange listing, or cash return.',
                ];
            }
        }

        return [
            'purchase_id' => $purchase->public_id,
            'purpose' => $purchase->purpose,
            'verification_payment_id' => $purchase->verification_payment_id ? (int) $purchase->verification_payment_id : null,
            'product_id' => $purchase->product_id,
            'base_plan_id' => $purchase->base_plan_id,
            'obfuscated_account_id' => $purchase->account_binding,
            'benefits' => $benefits,
            'status' => $purchase->status,
            'payment_status' => $purchase->status,
            'message' => $purchase->purpose === 'kyc'
                ? 'Complete the one-time Blockrium Verified membership payment. Identity review is separate; member benefits activate only after KYC approval and Google Play payment confirmation.'
                : 'Complete the Google Play purchase. Your mining boost activates only after Google confirms the purchase.',
        ];
    }

    private function markNeedsReview($purchase, string $reason): void
    {
        DB::table('google_play_purchases')->where('id', $purchase->id)->update([
            'status' => 'needs_review',
            'entitlement_status' => 'inactive',
            'metadata' => json_encode(['review_reason' => $reason]),
            'updated_at' => now(),
        ]);
    }

    private function recordRtdnEvent(string $messageId, string $eventType, ?string $tokenHash, string $status, ?string $error = null): void
    {
        DB::table('google_play_rtdn_events')->updateOrInsert(
            ['message_id' => $messageId],
            [
                'event_type' => $eventType,
                'purchase_token_hash' => $tokenHash,
                'status' => $status,
                'error_code' => $error,
                'processed_at' => in_array($status, ['processed', 'ignored', 'unmatched'], true) ? now() : null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function linkRtdnEvent(string $messageId, $purchase): void
    {
        $current = DB::table('google_play_purchases')->where('id', $purchase->id)->first();
        if (!$current) {
            return;
        }

        DB::table('google_play_rtdn_events')->where('message_id', $messageId)->update([
            'purchase_id' => (int) $current->id,
            'google_order_id' => $current->google_order_id,
            'store_amount' => $current->store_amount,
            'store_currency' => $current->store_currency,
            'updated_at' => now(),
        ]);
    }
}
