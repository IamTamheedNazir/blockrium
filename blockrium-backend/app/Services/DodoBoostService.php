<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DodoBoostService
{
    private $settings;

    public function __construct(IntegrationSettingsService $settings)
    {
        $this->settings = $settings;
    }

    public function createCheckoutSession($user, $plan, int $purchaseId): array
    {
        $apiKey = $this->setting('dodo_api_key', config('services.dodo.api_key'));
        $productId = trim((string) ($plan->provider_product_id ?? ''));

        if (!$apiKey || !$productId) {
            throw new HttpException(503, 'Dodo mining boost payments are not configured for this plan.');
        }

        $returnUrl = $this->setting(
            'dodo_boost_return_url',
            config('services.dodo.boost_return_url', config('app.url') . '/boost-payment-return')
        );

        $payload = [
            'product_cart' => [[
                'product_id' => $productId,
                'quantity' => 1,
            ]],
            'return_url' => $returnUrl,
            'metadata' => [
                'blockrium_feature' => 'mining_boost',
                'blockrium_purchase_id' => (string) $purchaseId,
                'blockrium_user_id' => (string) $user->id,
                'blockrium_plan_id' => (string) $plan->id,
                'blockrium_plan_slug' => (string) $plan->slug,
            ],
        ];

        if (!empty($user->email)) {
            $payload['customer'] = [
                'email' => (string) $user->email,
                'name' => (string) ($user->name ?: $user->username ?: 'Blockrium user'),
            ];
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post(rtrim($this->baseUrl(), '/') . '/checkouts', $payload);

        if (!$response->successful()) {
            Log::warning('Dodo boost checkout creation failed.', [
                'status' => $response->status(),
                'purchase_id' => $purchaseId,
                'response' => $this->safeResponse($response->json()),
            ]);
            throw new HttpException(502, 'Dodo could not start the mining boost payment.');
        }

        $data = $response->json();
        $checkoutUrl = $data['checkout_url'] ?? $data['checkoutUrl'] ?? null;
        if (!$checkoutUrl) {
            Log::warning('Dodo boost checkout response did not contain a checkout URL.', [
                'purchase_id' => $purchaseId,
            ]);
            throw new HttpException(502, 'Dodo returned an invalid checkout response.');
        }

        return [
            'checkout_url' => (string) $checkoutUrl,
            'session_id' => isset($data['session_id']) ? (string) $data['session_id'] : null,
            'client_secret' => isset($data['client_secret']) ? (string) $data['client_secret'] : null,
            'raw' => $this->safeResponse($data),
        ];
    }

    public function verifyWebhook(Request $request, string $rawBody): bool
    {
        $secret = trim((string) $this->setting('dodo_webhook_secret', config('services.dodo.webhook_secret')));
        if ($secret === '') {
            return false;
        }

        $messageId = (string) ($request->header('webhook-id') ?: $request->header('x-webhook-id'));
        $timestamp = (string) ($request->header('webhook-timestamp') ?: $request->header('x-webhook-timestamp'));
        $signatureHeader = (string) ($request->header('webhook-signature') ?: $request->header('x-webhook-signature'));

        if ($messageId !== '' && $timestamp !== '' && $signatureHeader !== '' && is_numeric($timestamp)) {
            if (abs(time() - (int) $timestamp) > 300) {
                return false;
            }

            $secretBytes = $secret;
            if (strpos($secret, 'whsec_') === 0) {
                $decoded = base64_decode(substr($secret, 6), true);
                if ($decoded !== false) {
                    $secretBytes = $decoded;
                }
            }

            $expected = base64_encode(hash_hmac('sha256', $messageId . '.' . $timestamp . '.' . $rawBody, $secretBytes, true));
            foreach (preg_split('/[[:space:]]+/', trim($signatureHeader)) as $candidate) {
                $parts = explode(',', $candidate, 2);
                $signature = count($parts) === 2 ? $parts[1] : $parts[0];
                if (hash_equals($expected, $signature)) {
                    return true;
                }
            }
        }

        // Older Dodo endpoints may expose a plain HMAC header instead of the
        // Standard Webhooks header set. Keep this fallback only for compatibility.
        foreach (['x-dodo-signature', 'x-signature'] as $header) {
            $provided = trim((string) $request->header($header));
            if ($provided === '') {
                continue;
            }

            $hex = hash_hmac('sha256', $rawBody, $secret);
            $base64 = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
            if (hash_equals($hex, $provided) || hash_equals($base64, $provided)) {
                return true;
            }
        }

        return false;
    }

    public function processWebhook(array $payload, ?string $headerEventId = null): array
    {
        if (!Schema::hasTable('dodo_boost_webhook_events') || !Schema::hasTable('user_boosts')) {
            throw new \RuntimeException('Dodo mining boost tables are not installed.');
        }

        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
        $eventType = strtolower(trim((string) ($payload['type'] ?? $payload['event_type'] ?? $payload['eventType'] ?? $data['type'] ?? '')));
        $eventId = (string) ($headerEventId ?: ($payload['id'] ?? $payload['event_id'] ?? $payload['message_id'] ?? $data['event_id'] ?? ''));
        if ($eventId === '') {
            $eventId = 'body:' . hash('sha256', json_encode($payload));
        }

        $paymentId = $this->firstValue($data, ['payment_id', 'paymentId', 'payment', 'id']);
        $subscriptionId = $this->firstValue($data, ['subscription_id', 'subscriptionId']);
        $metadata = $this->metadata($payload, $data);
        $purchaseId = (int) ($metadata['blockrium_purchase_id'] ?? $metadata['purchase_id'] ?? 0);
        $userId = (int) ($metadata['blockrium_user_id'] ?? $metadata['user_id'] ?? 0);
        $productId = $this->productId($data);

        $event = DB::table('dodo_boost_webhook_events')->where('event_id', $eventId)->first();
        if ($event) {
            return ['success' => true, 'duplicate' => true, 'event_id' => $eventId];
        }

        $eventRow = [
            'event_id' => $eventId,
            'event_type' => $eventType ?: null,
            'payment_id' => $paymentId,
            'subscription_id' => $subscriptionId,
            'status' => 'received',
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            DB::table('dodo_boost_webhook_events')->insert($eventRow);
        } catch (QueryException $exception) {
            // Dodo may retry concurrently; the unique event ID makes the second delivery safe.
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return ['success' => true, 'duplicate' => true, 'event_id' => $eventId];
            }

            throw $exception;
        }

        try {
            $purchase = $this->findPurchase($purchaseId, $paymentId, $subscriptionId, $userId, $productId);
            if (!$purchase) {
                $this->markEvent($eventId, 'ignored', null, null);
                return ['success' => true, 'processed' => false, 'message' => 'No matching Blockrium boost purchase.', 'event_id' => $eventId];
            }

            $eventData = [
                'payment_id' => $paymentId,
                'subscription_id' => $subscriptionId,
                'customer_id' => $this->firstValue($data, ['customer_id', 'customerId']),
                'product_id' => $productId,
                'amount' => $this->amount($data),
                'currency' => strtoupper((string) ($data['currency'] ?? $data['product_currency'] ?? $purchase->currency)),
                'period_start' => $this->dateValue($data, ['current_period_start', 'period_start', 'subscription_period_start']),
                'period_end' => $this->dateValue($data, ['current_period_end', 'period_end', 'expires_at', 'expiration_at', 'next_billing_date']),
            ];

            $result = $this->syncPurchase($purchase, $eventType, $eventData, $metadata);
            $this->markEvent($eventId, 'processed', (int) $purchase->id, null);

            return array_merge(['success' => true, 'event_id' => $eventId], $result);
        } catch (\Throwable $exception) {
            $this->markEvent($eventId, 'failed', null, $exception->getMessage());
            throw $exception;
        }
    }

    private function syncPurchase($purchase, string $eventType, array $event, array $metadata): array
    {
        $successEvents = [
            'payment.succeeded', 'payment_succeeded', 'subscription.active',
            'subscription.created', 'subscription.renewed', 'subscription.renewal',
            'subscription.updated', 'subscription.payment_succeeded',
        ];
        $pendingEvents = ['payment.processing', 'payment_pending', 'payment.pending', 'subscription.pending'];
        $failedEvents = ['payment.failed', 'payment_failed', 'payment.failure', 'subscription.payment_failed', 'subscription.past_due'];
        $cancelEvents = ['subscription.cancelled', 'subscription.canceled', 'subscription.expired', 'subscription.ended', 'subscription.paused'];
        $refundEvents = ['payment.refunded', 'payment.refund', 'payment.disputed', 'dispute.created'];

        $normalizedType = strtolower($eventType);
        $updates = [
            'updated_at' => now(),
            'provider_payment_id' => $event['payment_id'] ?: $purchase->provider_payment_id,
            'provider_subscription_id' => $event['subscription_id'] ?: $purchase->provider_subscription_id,
            'provider_customer_id' => $event['customer_id'] ?: $purchase->provider_customer_id,
        ];

        if ($event['product_id']) {
            $planProduct = DB::table('boost_plans')
                ->where('id', $purchase->boost_plan_id)
                ->value('provider_product_id');
            if ($planProduct && $planProduct !== $event['product_id']) {
                throw new \RuntimeException('Dodo product does not match the selected mining boost plan.');
            }
        }

        $reference = $event['subscription_id'] ?: ($event['payment_id'] ?: $purchase->purchase_reference);
        if ($reference) {
            $updates['purchase_reference'] = $reference;
        }
        if ($event['amount'] !== null) {
            $updates['amount_paid'] = $event['amount'];
        }
        if ($event['currency']) {
            $updates['currency'] = $event['currency'];
        }

        if (in_array($normalizedType, $successEvents, true)) {
            $periodEnd = $event['period_end'] ?: Carbon::now()->addDays(max(1, (int) $purchase->duration_days));
            $updates['status'] = 'active';
            $updates['payment_status'] = 'paid';
            $updates['starts_at'] = $event['period_start'] ?: ($purchase->starts_at ?: now());
            $updates['ends_at'] = $periodEnd;
            $updates['current_period_ends_at'] = $periodEnd;
            $updates['metadata'] = json_encode(array_merge((array) json_decode((string) $purchase->metadata, true), $metadata, ['last_event' => $normalizedType]));
        } elseif (in_array($normalizedType, $pendingEvents, true)) {
            $updates['status'] = $purchase->status === 'active' ? 'active' : 'pending';
            $updates['payment_status'] = 'processing';
        } elseif (in_array($normalizedType, $failedEvents, true)) {
            $updates['payment_status'] = 'failed';
            if ($purchase->status !== 'active' || !$purchase->ends_at || Carbon::parse($purchase->ends_at)->isPast()) {
                $updates['status'] = 'failed';
                $updates['ends_at'] = now();
            }
        } elseif (in_array($normalizedType, $refundEvents, true)) {
            $updates['status'] = 'cancelled';
            $updates['payment_status'] = 'refunded';
            $updates['ends_at'] = now();
            $updates['current_period_ends_at'] = now();
        } elseif (in_array($normalizedType, $cancelEvents, true)) {
            $updates['cancel_at_period_end'] = true;
            $updates['payment_status'] = 'cancelled';
            if (!$purchase->ends_at || Carbon::parse($purchase->ends_at)->isPast()) {
                $updates['status'] = 'expired';
                $updates['ends_at'] = now();
            }
        } else {
            return ['processed' => false, 'message' => 'Event recorded but not an activation event.'];
        }

        DB::table('user_boosts')->where('id', $purchase->id)->update($updates);

        return [
            'processed' => true,
            'purchase_id' => (int) $purchase->id,
            'status' => $updates['status'] ?? $purchase->status,
            'payment_status' => $updates['payment_status'] ?? $purchase->payment_status,
        ];
    }

    private function findPurchase(int $purchaseId, ?string $paymentId, ?string $subscriptionId, int $userId, ?string $productId)
    {
        $query = DB::table('user_boosts as b')
            ->leftJoin('boost_plans as p', 'b.boost_plan_id', '=', 'p.id')
            ->where('b.provider', 'dodo')
            ->select('b.*', 'p.duration_days', 'p.provider_product_id as plan_product_id');

        if ($purchaseId > 0) {
            $purchase = (clone $query)->where('b.id', $purchaseId)->first();
            if ($purchase) {
                return $purchase;
            }
        }

        if ($paymentId || $subscriptionId) {
            $purchase = (clone $query)->where(function ($where) use ($paymentId, $subscriptionId) {
                if ($paymentId) {
                    $where->where('b.provider_payment_id', $paymentId)->orWhere('b.purchase_reference', $paymentId);
                }
                if ($subscriptionId) {
                    $where->orWhere('b.provider_subscription_id', $subscriptionId)->orWhere('b.purchase_reference', $subscriptionId);
                }
            })->orderByDesc('b.id')->first();
            if ($purchase) {
                return $purchase;
            }
        }

        if ($userId > 0) {
            $query->where('b.user_id', $userId)->whereIn('b.status', ['pending', 'active']);
        }
        if ($productId) {
            $query->where('p.provider_product_id', $productId);
        }

        return $query->orderByDesc('b.id')->first();
    }

    private function metadata(array $payload, array $data): array
    {
        $metadata = $data['metadata'] ?? $payload['metadata'] ?? [];
        return is_array($metadata) ? $metadata : [];
    }

    private function productId(array $data): ?string
    {
        $product = $this->firstValue($data, ['product_id', 'productId']);
        if (!$product && isset($data['product']) && is_array($data['product'])) {
            $product = $data['product']['product_id'] ?? $data['product']['id'] ?? null;
        }
        if (!$product && isset($data['product_cart'][0]['product_id'])) {
            $product = $data['product_cart'][0]['product_id'];
        }
        return $product ? (string) $product : null;
    }

    private function firstValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key]) && (string) $data[$key] !== '') {
                return (string) $data[$key];
            }
        }
        return null;
    }

    private function dateValue(array $data, array $keys): ?Carbon
    {
        foreach ($keys as $key) {
            if (!empty($data[$key])) {
                try {
                    return is_numeric($data[$key])
                        ? Carbon::createFromTimestamp((int) $data[$key])
                        : Carbon::parse($data[$key]);
                } catch (\Throwable $exception) {
                    return null;
                }
            }
        }
        return null;
    }

    private function amount(array $data): ?float
    {
        foreach (['total_amount', 'amount', 'paid_amount', 'product_price'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                $amount = (float) $data[$key];
                return $amount > 1000 ? round($amount / 100, 2) : $amount;
            }
        }
        return null;
    }

    private function markEvent(string $eventId, string $status, ?int $purchaseId, ?string $error): void
    {
        DB::table('dodo_boost_webhook_events')->where('event_id', $eventId)->update([
            'status' => $status,
            'user_boost_id' => $purchaseId,
            'processing_error' => $error ? substr($error, 0, 1000) : null,
            'processed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function baseUrl(): string
    {
        return (string) $this->setting('dodo_base_url', config('services.dodo.base_url', 'https://live.dodopayments.com'));
    }

    private function setting(string $key, $fallback = null)
    {
        return $this->settings->get($key, $fallback);
    }

    private function safeResponse($response)
    {
        if (!is_array($response)) {
            return null;
        }

        foreach (['api_key', 'authorization', 'client_secret'] as $key) {
            if (array_key_exists($key, $response)) {
                $response[$key] = '[redacted]';
            }
        }
        return $response;
    }
}
