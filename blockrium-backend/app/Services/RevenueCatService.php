<?php

namespace App\Services;

use App\Models\RevenueCatEvent;
use App\Models\RevenueCatSubscription;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RevenueCatService
{
    private const ACTIVE_EVENTS = [
        'INITIAL_PURCHASE',
        'RENEWAL',
        'UNCANCELLATION',
        'NON_RENEWING_PURCHASE',
        'SUBSCRIPTION_EXTENDED',
        'TEMPORARY_ENTITLEMENT_GRANT',
        'PURCHASE_REDEEMED',
    ];

    private const PASSIVE_EVENTS = [
        'BILLING_ISSUE',
        'SUBSCRIPTION_PAUSED',
        'CANCELLATION',
        'PRODUCT_CHANGE',
    ];

    private const PURCHASE_REVENUE_EVENTS = [
        'INITIAL_PURCHASE',
        'RENEWAL',
        'NON_RENEWING_PURCHASE',
        'PURCHASE_REDEEMED',
    ];

    private const PLAN_LABELS = [
        'yearly' => 'Yearly',
        'six_month' => 'Six Month',
        'three_month' => 'Three Month',
        'monthly' => 'Monthly',
    ];

    public function processWebhook(array $payload): array
    {
        $event = $this->validateWebhookPayload($payload);

        try {
            return DB::transaction(function () use ($event, $payload) {
                return $this->processValidatedEvent($event, $payload);
            }, 3);
        } catch (QueryException $error) {
            if ($this->isDuplicateEventException($error)) {
                return ['success' => true, 'duplicate' => true];
            }

            throw $error;
        }
    }

    public function subscriptionStats(): array
    {
        $planCounts = array_fill_keys(array_keys(self::PLAN_LABELS), 0);
        $activeWindow = $this->activeSubscriptionWindow();

        $stats = [
            'total_pro_users' => 0,
            'active_subscriptions' => 0,
            'expired_subscriptions' => 0,
            'plan_counts' => $planCounts,
            'revenue' => $this->revenueMetrics(),
            'history' => [],
        ];

        if ($this->hasColumn('res_users', 'is_pro')) {
            $stats['total_pro_users'] = DB::table('res_users')->where('is_pro', 1)->count();
        }

        if (Schema::hasTable('revenuecat_subscriptions')) {
            $stats['active_subscriptions'] = DB::table('revenuecat_subscriptions')
                ->where($activeWindow)
                ->count();

            $stats['expired_subscriptions'] = DB::table('revenuecat_subscriptions')
                ->where(function ($query) {
                    $query->whereIn('status', ['expired', 'refunded', 'inactive', 'transferred'])
                        ->orWhere(function ($expiredAccess) {
                            $expiredAccess->whereNotNull('expires_at')
                                ->where('expires_at', '<', now());
                        });
                })
                ->count();

            $groupedPlans = DB::table('revenuecat_subscriptions')
                ->select('product_id', DB::raw('COUNT(*) as total'))
                ->where($activeWindow)
                ->groupBy('product_id')
                ->get();

            foreach ($groupedPlans as $plan) {
                $key = $this->subscriptionProductKey($plan->product_id);
                if ($key && isset($stats['plan_counts'][$key])) {
                    $stats['plan_counts'][$key] += (int) $plan->total;
                }
            }
        }

        $stats['history'] = $this->subscriptionHistory(20);

        return $stats;
    }

    public function subscriptionUsers(array $filters = [])
    {
        if (!Schema::hasTable('revenuecat_subscriptions')) {
            return collect();
        }

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 25)));
        $status = trim((string) ($filters['status'] ?? ''));
        $plan = trim((string) ($filters['plan'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        $query = DB::table('revenuecat_subscriptions')
            ->leftJoin('res_users', 'revenuecat_subscriptions.user_id', '=', 'res_users.id')
            ->select(
                'revenuecat_subscriptions.*',
                'res_users.username',
                'res_users.name as user_name',
                'res_users.email',
                'res_users.is_pro',
                'res_users.subscription_status',
                'res_users.subscription_expiry'
            )
            ->orderByDesc('revenuecat_subscriptions.last_event_at');

        if ($status !== '') {
            $query->where('revenuecat_subscriptions.status', $status);
        }

        if ($plan !== '') {
            $query->where(function ($where) use ($plan) {
                $where->where('revenuecat_subscriptions.product_id', $plan)
                    ->orWhere('revenuecat_subscriptions.product_id', 'like', '%:' . $plan);
            });
        }

        if ($search !== '') {
            $query->where(function ($where) use ($search) {
                $where->where('res_users.username', 'like', '%' . $search . '%')
                    ->orWhere('res_users.email', 'like', '%' . $search . '%')
                    ->orWhere('revenuecat_subscriptions.app_user_id', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate($perPage);
    }

    public function revenueMetrics(): array
    {
        $metrics = [
            'gross_usd' => 0,
            'refunds_usd' => 0,
            'net_usd' => 0,
            'this_month_usd' => 0,
            'last_30_days_usd' => 0,
            'total' => 0,
            'this_month' => 0,
            'last_30_days' => 0,
            'by_plan_usd' => array_fill_keys(array_keys(self::PLAN_LABELS), 0),
            'by_plan' => array_fill_keys(array_keys(self::PLAN_LABELS), 0),
            'by_currency' => [],
            'by_store' => [],
            'recent_events' => [],
        ];

        if (!Schema::hasTable('revenuecat_events')) {
            return $metrics;
        }

        $priceUsdColumn = $this->hasColumn('revenuecat_events', 'price_usd') ? 'price_usd' : 'price';
        $priceCurrencyColumn = $this->hasColumn('revenuecat_events', 'price_in_purchased_currency')
            ? 'price_in_purchased_currency'
            : 'price';

        $purchaseQuery = DB::table('revenuecat_events')
            ->whereIn('event_type', self::PURCHASE_REVENUE_EVENTS)
            ->where($priceUsdColumn, '>', 0);

        $metrics['gross_usd'] = (float) (clone $purchaseQuery)->sum($priceUsdColumn);
        $metrics['this_month_usd'] = (float) (clone $purchaseQuery)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum($priceUsdColumn);
        $metrics['last_30_days_usd'] = (float) (clone $purchaseQuery)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->sum($priceUsdColumn);

        $metrics['refunds_usd'] = (float) DB::table('revenuecat_events')
            ->where($priceUsdColumn, '<', 0)
            ->sum($priceUsdColumn);
        $metrics['net_usd'] = $metrics['gross_usd'] + $metrics['refunds_usd'];

        $metrics['total'] = $metrics['gross_usd'];
        $metrics['this_month'] = $metrics['this_month_usd'];
        $metrics['last_30_days'] = $metrics['last_30_days_usd'];

        $byPlan = (clone $purchaseQuery)
            ->select('product_id', DB::raw('SUM(' . $priceUsdColumn . ') as total'))
            ->groupBy('product_id')
            ->get();

        foreach ($byPlan as $plan) {
            $key = $this->subscriptionProductKey($plan->product_id);
            if ($key && isset($metrics['by_plan_usd'][$key])) {
                $metrics['by_plan_usd'][$key] += (float) $plan->total;
                $metrics['by_plan'][$key] = $metrics['by_plan_usd'][$key];
            }
        }

        $metrics['by_currency'] = DB::table('revenuecat_events')
            ->select('currency', DB::raw('SUM(' . $priceCurrencyColumn . ') as gross'), DB::raw('COUNT(*) as events'))
            ->whereIn('event_type', self::PURCHASE_REVENUE_EVENTS)
            ->where($priceCurrencyColumn, '>', 0)
            ->groupBy('currency')
            ->get();

        $metrics['by_store'] = DB::table('revenuecat_events')
            ->select('store', DB::raw('SUM(' . $priceUsdColumn . ') as gross_usd'), DB::raw('COUNT(*) as events'))
            ->whereIn('event_type', self::PURCHASE_REVENUE_EVENTS)
            ->where($priceUsdColumn, '>', 0)
            ->groupBy('store')
            ->get();

        $metrics['recent_events'] = $this->subscriptionHistory(50);

        return $metrics;
    }

    public function subscriptionHistory(int $limit = 50)
    {
        if (!Schema::hasTable('revenuecat_events')) {
            return [];
        }

        return DB::table('revenuecat_events')
            ->leftJoin('res_users', 'revenuecat_events.user_id', '=', 'res_users.id')
            ->select(
                'revenuecat_events.*',
                'res_users.username',
                'res_users.name as user_name',
                'res_users.email'
            )
            ->orderByDesc('revenuecat_events.created_at')
            ->limit($limit)
            ->get();
    }

    private function processValidatedEvent(array $event, array $payload): array
    {
        $eventId = (string) $event['id'];
        $eventType = strtoupper((string) $event['type']);

        if (RevenueCatEvent::where('event_id', $eventId)->lockForUpdate()->exists()) {
            return ['success' => true, 'duplicate' => true];
        }

        $user = $this->resolveUserFromEvent($event);
        $appUserId = $this->appUserIdFromEvent($event);
        $productId = $this->eventProductId($event);
        $entitlementIds = $this->entitlementIds($event);
        $entitlementId = $this->primaryEntitlementId($entitlementIds);
        $boostPlan = $this->findBoostPlan($productId);
        $purchasedAt = $this->carbonFromMillis($event['purchased_at_ms'] ?? null);
        $expirationAt = $this->carbonFromMillis($event['expiration_at_ms'] ?? null);
        $priceUsd = $this->numericValue($event['price'] ?? null);
        $priceInPurchasedCurrency = $this->numericValue($event['price_in_purchased_currency'] ?? null);
        $currency = $event['currency'] ?? null;

        if ($priceInPurchasedCurrency === null) {
            $priceInPurchasedCurrency = $priceUsd;
        }

        $this->storeWebhookLog($payload, $event, $eventType, $user, $appUserId, $productId, $entitlementIds, $purchasedAt, $expirationAt, $priceUsd, $priceInPurchasedCurrency, $currency);

        if ($eventType === 'TRANSFER') {
            return $this->processTransfer($event, $appUserId);
        }

        if ($eventType === 'TEST') {
            return ['success' => true, 'processed' => false, 'message' => 'Test event received'];
        }

        if (!$user) {
            Log::warning('RevenueCat webhook user not found', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'app_user_id' => $appUserId,
            ]);

            return [
                'success' => true,
                'processed' => false,
                'message' => 'User not found for RevenueCat customer id',
            ];
        }

        if (!$this->isRelevantSubscription($entitlementIds, $productId, $boostPlan)) {
            return ['success' => true, 'processed' => false, 'message' => 'Event ignored'];
        }

        $status = $this->subscriptionStatus($eventType, $event, $expirationAt);
        $hasAccess = $this->hasActiveAccess($eventType, $event, $expirationAt);
        $planName = $boostPlan->name ?? $this->labelForProduct($productId);
        $purchaseReference = $event['original_transaction_id'] ?? ($event['transaction_id'] ?? null);

        $this->lockRevenueCatBoosts((int) $user->id);
        $this->upsertSubscription($user, $appUserId, $entitlementId, $productId, $planName, $status, $event, $purchasedAt, $expirationAt, $priceUsd, $priceInPurchasedCurrency, $currency);
        $this->syncUserSubscription($user, $appUserId, $entitlementId, $productId, $planName, $status, $hasAccess, $eventType, $expirationAt, $event);

        if (!$hasAccess) {
            $this->deactivateRevenueCatBoosts((int) $user->id, $status === 'refunded' ? 'cancelled' : 'expired', $status, $purchaseReference);
        }

        $this->syncUserBoost($user, $boostPlan, $purchaseReference, $status, $hasAccess, $event, $purchasedAt, $expirationAt, $priceUsd, $priceInPurchasedCurrency, $currency);

        return [
            'success' => true,
            'processed' => true,
            'user_id' => (int) $user->id,
            'status' => $status,
            'is_pro' => $hasAccess,
        ];
    }

    private function validateWebhookPayload(array $payload): array
    {
        if (!isset($payload['event']) || !is_array($payload['event'])) {
            throw new \InvalidArgumentException('RevenueCat webhook payload must contain an event object.');
        }

        $event = $payload['event'];
        $eventType = strtoupper(trim((string) ($event['type'] ?? '')));

        if (empty($event['id']) || !is_string($event['id'])) {
            throw new \InvalidArgumentException('RevenueCat webhook event id is required.');
        }

        if ($eventType === '') {
            throw new \InvalidArgumentException('RevenueCat webhook event type is required.');
        }

        if ($eventType !== 'TRANSFER' && $eventType !== 'TEST' && !$this->appUserIdFromEvent($event)) {
            throw new \InvalidArgumentException('RevenueCat webhook event must contain an app_user_id, original_app_user_id, or alias.');
        }

        if ($eventType === 'TRANSFER' && empty($event['transferred_to']) && empty($event['transferred_from'])) {
            throw new \InvalidArgumentException('RevenueCat transfer event must contain transferred_to or transferred_from.');
        }

        return $event;
    }

    private function activeSubscriptionWindow(): \Closure
    {
        return function ($query) {
            $query->whereIn('status', ['active', 'cancelled', 'billing_issue', 'paused'])
                ->where(function ($where) {
                    $where->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                });
        };
    }

    private function processTransfer(array $event, ?string $appUserId): array
    {
        $fromUsers = [];
        $toUsers = [];

        foreach ((array) ($event['transferred_from'] ?? []) as $fromCustomerId) {
            $user = $this->findUserByRevenueCatId((string) $fromCustomerId);
            if ($user) {
                $this->markTransferredAway($user, (string) $fromCustomerId);
                $fromUsers[] = (int) $user->id;
            }
        }

        foreach ((array) ($event['transferred_to'] ?? []) as $toCustomerId) {
            $user = $this->findUserByRevenueCatId((string) $toCustomerId);
            if ($user) {
                $this->syncCustomerId($user, (string) $toCustomerId);
                $toUsers[] = (int) $user->id;
            }
        }

        return [
            'success' => true,
            'processed' => true,
            'event_type' => 'TRANSFER',
            'app_user_id' => $appUserId,
            'transferred_from_users' => $fromUsers,
            'transferred_to_users' => $toUsers,
        ];
    }

    private function markTransferredAway($user, string $customerId): void
    {
        if ($this->hasColumn('res_users', 'is_pro')) {
            DB::table('res_users')->where('id', $user->id)->update([
                'is_pro' => 0,
                'subscription_status' => 'transferred',
                'subscription_last_event' => 'TRANSFER',
                'subscription_updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('revenuecat_subscriptions')) {
            RevenueCatSubscription::where('user_id', $user->id)->update([
                'status' => 'transferred',
                'last_event_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->deactivateRevenueCatBoosts((int) $user->id, 'cancelled', 'transferred');
    }

    private function syncCustomerId($user, string $customerId): void
    {
        $data = [];

        foreach (['revenuecat_customer_id', 'revenuecat_app_user_id'] as $column) {
            if ($this->hasColumn('res_users', $column)) {
                $data[$column] = $customerId;
            }
        }

        if ($data) {
            DB::table('res_users')->where('id', $user->id)->update($data);
        }
    }

    private function appUserIdFromEvent(array $event): ?string
    {
        return $event['app_user_id']
            ?? $event['original_app_user_id']
            ?? ($event['transferred_to'][0] ?? null)
            ?? ($event['aliases'][0] ?? null);
    }

    private function resolveUserFromEvent(array $event)
    {
        if (!Schema::hasTable('res_users')) {
            return null;
        }

        $candidates = array_filter(array_unique(array_merge(
            [$event['app_user_id'] ?? null, $event['original_app_user_id'] ?? null],
            (array) ($event['aliases'] ?? []),
            (array) ($event['transferred_to'] ?? [])
        )));

        foreach ($candidates as $candidate) {
            $user = $this->findUserByRevenueCatId((string) $candidate);
            if ($user) {
                return $user;
            }
        }

        return null;
    }

    private function findUserByRevenueCatId(string $appUserId)
    {
        $normalized = trim($appUserId);
        if ($normalized === '') {
            return null;
        }

        if (ctype_digit($normalized)) {
            $user = DB::table('res_users')->where('id', (int) $normalized)->first();
            if ($user) {
                return $user;
            }
        }

        if (strpos($normalized, 'blockrium_') === 0) {
            $legacyId = substr($normalized, strlen('blockrium_'));
            if (ctype_digit($legacyId)) {
                $user = DB::table('res_users')->where('id', (int) $legacyId)->first();
                if ($user) {
                    return $user;
                }
            }
        }

        foreach (['revenuecat_customer_id', 'revenuecat_app_user_id'] as $column) {
            if ($this->hasColumn('res_users', $column)) {
                $user = DB::table('res_users')->where($column, $normalized)->first();
                if ($user) {
                    return $user;
                }
            }
        }

        return null;
    }

    private function entitlementIds(array $event): array
    {
        $ids = $event['entitlement_ids'] ?? [];

        if (!$ids && !empty($event['entitlement_id'])) {
            $ids = [$event['entitlement_id']];
        }

        return array_values(array_filter((array) $ids));
    }

    private function primaryEntitlementId(array $entitlementIds): ?string
    {
        $expected = env('REVENUECAT_PRO_ENTITLEMENT_ID', 'blockrium Pro');

        if (in_array($expected, $entitlementIds, true)) {
            return $expected;
        }

        return $entitlementIds[0] ?? $expected;
    }

    private function eventProductId(array $event): ?string
    {
        $productId = $event['product_id'] ?? null;

        if (!$productId && !empty($event['new_product_id'])) {
            $productId = $event['new_product_id'];
        }

        return $this->normalizeProductId($productId);
    }

    private function normalizeProductId($productId): ?string
    {
        return $productId ? trim((string) $productId) : null;
    }

    private function productLookupIds(?string $productId): array
    {
        if (!$productId) {
            return [];
        }

        $ids = [$productId];

        if (strpos($productId, ':') !== false) {
            $parts = explode(':', $productId);
            $ids[] = end($parts);
        }

        return array_values(array_unique($ids));
    }

    private function findBoostPlan(?string $productId)
    {
        if (!$productId || !Schema::hasTable('boost_plans') || !$this->hasColumn('boost_plans', 'provider_product_id')) {
            return null;
        }

        return DB::table('boost_plans')
            ->where('provider', 'revenuecat')
            ->whereIn('provider_product_id', $this->productLookupIds($productId))
            ->orderByDesc('status')
            ->first();
    }

    private function isRelevantSubscription(array $entitlementIds, ?string $productId, $boostPlan): bool
    {
        $expected = env('REVENUECAT_PRO_ENTITLEMENT_ID', 'blockrium Pro');

        return in_array($expected, $entitlementIds, true) || $boostPlan !== null || $this->subscriptionProductKey($productId) !== null;
    }

    private function carbonFromMillis($value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        return Carbon::createFromTimestamp(((int) $value) / 1000);
    }

    private function subscriptionStatus(string $eventType, array $event, ?Carbon $expirationAt): string
    {
        if ($eventType === 'EXPIRATION') {
            return 'expired';
        }

        if ($eventType === 'CANCELLATION') {
            $cancelReason = strtoupper((string) ($event['cancel_reason'] ?? ''));
            if (in_array($cancelReason, ['CUSTOMER_SUPPORT', 'DEVELOPER_INITIATED'], true)) {
                return 'refunded';
            }

            return 'cancelled';
        }

        if ($eventType === 'BILLING_ISSUE') {
            return 'billing_issue';
        }

        if ($eventType === 'SUBSCRIPTION_PAUSED') {
            return 'paused';
        }

        if ($eventType === 'PRODUCT_CHANGE') {
            return $expirationAt && $expirationAt->isFuture() ? 'active' : 'inactive';
        }

        if (in_array($eventType, self::ACTIVE_EVENTS, true)) {
            return 'active';
        }

        if (in_array($eventType, self::PASSIVE_EVENTS, true)) {
            return $expirationAt && $expirationAt->isFuture() ? 'active' : 'inactive';
        }

        return 'inactive';
    }

    private function hasActiveAccess(string $eventType, array $event, ?Carbon $expirationAt): bool
    {
        if ($eventType === 'EXPIRATION') {
            return false;
        }

        if ($eventType === 'CANCELLATION') {
            $cancelReason = strtoupper((string) ($event['cancel_reason'] ?? ''));
            if (in_array($cancelReason, ['CUSTOMER_SUPPORT', 'DEVELOPER_INITIATED'], true)) {
                return false;
            }
        }

        if ($expirationAt) {
            return $expirationAt->isFuture();
        }

        return in_array($eventType, self::ACTIVE_EVENTS, true);
    }

    private function storeWebhookLog(array $payload, array $event, string $eventType, $user, ?string $appUserId, ?string $productId, array $entitlementIds, ?Carbon $purchasedAt, ?Carbon $expirationAt, ?float $priceUsd, ?float $priceInPurchasedCurrency, ?string $currency): void
    {
        if (!Schema::hasTable('revenuecat_events')) {
            throw new \RuntimeException('revenuecat_events table is missing. Run migrations before enabling RevenueCat webhooks.');
        }

        $data = [
            'event_id' => $event['id'],
            'user_id' => $user->id ?? null,
            'app_user_id' => $appUserId,
            'event_type' => $eventType,
            'product_id' => $productId,
            'entitlement_ids' => json_encode($entitlementIds),
            'transaction_id' => $event['transaction_id'] ?? null,
            'original_transaction_id' => $event['original_transaction_id'] ?? null,
            'store' => $event['store'] ?? null,
            'price' => $priceInPurchasedCurrency,
            'currency' => $currency,
            'purchased_at' => $purchasedAt ? $purchasedAt->toDateTimeString() : null,
            'expiration_at' => $expirationAt ? $expirationAt->toDateTimeString() : null,
            'raw_payload' => json_encode($payload),
            'processed_at' => now(),
        ];

        if ($this->hasColumn('revenuecat_events', 'price_usd')) {
            $data['price_usd'] = $priceUsd;
        }

        if ($this->hasColumn('revenuecat_events', 'price_in_purchased_currency')) {
            $data['price_in_purchased_currency'] = $priceInPurchasedCurrency;
        }

        RevenueCatEvent::create($data);
    }

    private function upsertSubscription($user, ?string $appUserId, ?string $entitlementId, ?string $productId, ?string $planName, string $status, array $event, ?Carbon $purchasedAt, ?Carbon $expirationAt, ?float $priceUsd, ?float $priceInPurchasedCurrency, ?string $currency): void
    {
        if (!Schema::hasTable('revenuecat_subscriptions')) {
            throw new \RuntimeException('revenuecat_subscriptions table is missing. Run migrations before enabling RevenueCat webhooks.');
        }

        $data = [
            'app_user_id' => $appUserId ?: (string) $user->id,
            'product_id' => $productId,
            'plan_name' => $planName,
            'status' => $status,
            'store' => $event['store'] ?? null,
            'environment' => $event['environment'] ?? null,
            'transaction_id' => $event['transaction_id'] ?? null,
            'original_transaction_id' => $event['original_transaction_id'] ?? null,
            'price' => $priceInPurchasedCurrency,
            'currency' => $currency,
            'purchased_at' => $purchasedAt ? $purchasedAt->toDateTimeString() : null,
            'expires_at' => $expirationAt ? $expirationAt->toDateTimeString() : null,
            'last_event_at' => now(),
            'metadata' => json_encode($event),
        ];

        if ($this->hasColumn('revenuecat_subscriptions', 'price_usd')) {
            $data['price_usd'] = $priceUsd;
        }

        if ($this->hasColumn('revenuecat_subscriptions', 'price_in_purchased_currency')) {
            $data['price_in_purchased_currency'] = $priceInPurchasedCurrency;
        }

        RevenueCatSubscription::updateOrCreate(
            [
                'user_id' => (int) $user->id,
                'entitlement_id' => $entitlementId,
            ],
            $data
        );
    }

    private function syncUserSubscription($user, ?string $appUserId, ?string $entitlementId, ?string $productId, ?string $planName, string $status, bool $hasAccess, string $eventType, ?Carbon $expirationAt, array $event): void
    {
        if (!Schema::hasTable('res_users')) {
            return;
        }

        $columns = [
            'is_pro' => $hasAccess ? 1 : 0,
            'subscription_status' => $status,
            'subscription_type' => $planName ?: $this->labelForProduct($productId),
            'subscription_expiry' => $expirationAt ? $expirationAt->toDateTimeString() : null,
            'revenuecat_customer_id' => $appUserId ?: (string) $user->id,
            'revenuecat_app_user_id' => $appUserId ?: (string) $user->id,
            'revenuecat_product_id' => $productId,
            'revenuecat_entitlement_id' => $entitlementId,
            'subscription_store' => $event['store'] ?? null,
            'subscription_last_event' => $eventType,
            'subscription_updated_at' => now(),
        ];

        $data = [];
        foreach ($columns as $column => $value) {
            if ($this->hasColumn('res_users', $column)) {
                $data[$column] = $value;
            }
        }

        if ($data) {
            DB::table('res_users')->where('id', $user->id)->update($data);
        }
    }

    private function lockRevenueCatBoosts(int $userId): void
    {
        if (!Schema::hasTable('user_boosts')) {
            return;
        }

        DB::table('user_boosts')
            ->where('user_id', $userId)
            ->where('provider', 'revenuecat')
            ->lockForUpdate()
            ->get();
    }

    private function deactivateRevenueCatBoosts(int $userId, string $boostStatus, string $paymentStatus, ?string $exceptReference = null): void
    {
        if (!Schema::hasTable('user_boosts')) {
            return;
        }

        $query = DB::table('user_boosts')
            ->where('user_id', $userId)
            ->where('provider', 'revenuecat')
            ->where('status', 'active');

        if ($exceptReference) {
            $query->where('purchase_reference', '!=', $exceptReference);
        }

        $query->update([
            'status' => $boostStatus,
            'payment_status' => $paymentStatus,
            'ends_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function syncUserBoost($user, $boostPlan, ?string $purchaseReference, string $status, bool $hasAccess, array $event, ?Carbon $purchasedAt, ?Carbon $expirationAt, ?float $priceUsd, ?float $priceInPurchasedCurrency, ?string $currency): void
    {
        if (!$boostPlan || !Schema::hasTable('user_boosts')) {
            return;
        }

        $reference = $purchaseReference ?: sprintf('revenuecat:%s:%s', $user->id, $event['product_id'] ?? 'unknown');
        $boostStatus = $hasAccess ? 'active' : ($status === 'refunded' ? 'cancelled' : 'expired');
        $paymentStatus = $hasAccess ? 'paid' : ($status === 'refunded' ? 'refunded' : $status);

        if ($hasAccess) {
            $this->deactivateRevenueCatBoosts((int) $user->id, 'expired', 'replaced', $reference);
        }

        DB::table('user_boosts')->updateOrInsert(
            [
                'provider' => 'revenuecat',
                'purchase_reference' => $reference,
            ],
            [
                'user_id' => (int) $user->id,
                'boost_plan_id' => $boostPlan->id,
                'amount_paid' => $priceInPurchasedCurrency !== null ? $priceInPurchasedCurrency : 0,
                'currency' => $currency ?: ($boostPlan->currency ?? 'USD'),
                'boost_percent' => $boostPlan->boost_percent,
                'starts_at' => $purchasedAt ? $purchasedAt->toDateTimeString() : now(),
                'ends_at' => $expirationAt ? $expirationAt->toDateTimeString() : null,
                'status' => $boostStatus,
                'payment_status' => $paymentStatus,
                'metadata' => json_encode(array_merge($event, [
                    'price_usd' => $priceUsd,
                    'price_in_purchased_currency' => $priceInPurchasedCurrency,
                ])),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function labelForProduct(?string $productId): ?string
    {
        $key = $this->subscriptionProductKey($productId);

        return $key ? self::PLAN_LABELS[$key] : $productId;
    }

    private function subscriptionProductKey($productId): ?string
    {
        if (!$productId) {
            return null;
        }

        $ids = $this->productLookupIds((string) $productId);

        foreach ($ids as $id) {
            if (array_key_exists($id, self::PLAN_LABELS)) {
                return $id;
            }
        }

        return null;
    }

    private function numericValue($value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function isDuplicateEventException(QueryException $error): bool
    {
        $driverCode = $error->errorInfo[1] ?? null;

        return $driverCode === 1062 && strpos($error->getMessage(), 'event_id') !== false;
    }

    private function hasColumn($table, $column): bool
    {
        try {
            return Schema::hasTable($table) && Schema::hasColumn($table, $column);
        } catch (\Throwable $error) {
            return false;
        }
    }
}
