<?php

namespace App\Http\Controllers;

use App\Services\EngagementAdminService;
use App\Services\IntegrationSettingsService;
use App\Services\VerifiedMemberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Session;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VerifiedMemberAdminController extends Controller
{
    private $members;
    private $audit;
    private $settings;
    public function __construct(VerifiedMemberService $members, EngagementAdminService $audit, IntegrationSettingsService $settings)
    {
        $this->members = $members;
        $this->audit = $audit;
        $this->settings = $settings;
        $this->middleware('checklogin');
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => 'nullable|in:not_started,started,submitted,in_review,in_progress,approved,rejected,revoked,access_revoked,country_limit,payment_pending,verified',
            'payment_status' => 'nullable|in:pending,paid,failed,cancelled,refunded,revoked',
            'search' => 'nullable|string|max:120',
        ]);

        return view('verified-members.index', [
            'memberships' => $this->members->pendingForAdmin($filters),
            'summary' => $this->members->adminSummary(),
            'filters' => $filters,
        ]);
    }

    public function syncDidit(Request $request)
    {
        $input = $request->validate(['offset' => 'nullable|integer|min:0|max:10000000']);
        $offset = (int) ($input['offset'] ?? 0);

        try {
            $stats = $this->members->reconcileDiditSessions($offset);
        } catch (\Throwable $error) {
            report($error);
            return back()->withErrors(['didit_sync' => 'Didit could not be synchronized. Check the integration settings and server logs, then try again.']);
        }

        $this->audit->recordAdminAudit($this->adminId(), 'reconcile', 'didit_sessions', null, $stats);
        $message = sprintf(
            'Didit sync checked %d sessions from offset %d of %d listed: %d linked, %d updated, %d newly approved with payment pending, %d protected, %d unlinked.',
            $stats['inspected'],
            $stats['offset'],
            $stats['provider_count'],
            $stats['linked'],
            $stats['updated'],
            $stats['approved_payment_pending'],
            $stats['protected'],
            $stats['unlinked']
        );
        if ($stats['has_more']) {
            $message .= sprintf(' More sessions remain. Run the next 100-session batch starting at offset %d.', $stats['next_offset']);
        }

        return back()->with('success', $message)->with('didit_sync_next_offset', $stats['has_more'] ? $stats['next_offset'] : 0);
    }

    public function settings()
    {
        $googlePlayBoostPlans = Schema::hasTable('boost_plans')
            ? DB::table('boost_plans')->where('status', 1)->orderBy('sort_order')->orderBy('id')->get()->map(function ($plan) {
                $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', (string) $plan->slug), '_'));
                $prefix = 'google_play_' . $slug;
                return [
                    'id' => (int) $plan->id,
                    'name' => $plan->name,
                    'boost_percent' => (float) $plan->boost_percent,
                    'product_id' => $this->settings->get($prefix . '_product_id', ''),
                    'base_plan_id' => $this->settings->get($prefix . '_base_plan_id', ''),
                ];
            })
            : collect();

        return view('verified-members.settings', [
            'settings' => [
                'didit_api_key' => $this->settings->masked('didit_api_key', config('services.didit.api_key')),
                'didit_webhook_secret' => $this->settings->masked('didit_webhook_secret', config('services.didit.webhook_secret')),
                'didit_workflow_id' => $this->settings->get('didit_workflow_id', config('services.didit.workflow_id')),
                'didit_base_url' => $this->settings->get('didit_base_url', config('services.didit.base_url')),
                'didit_callback_url' => $this->settings->get('didit_callback_url', config('services.didit.callback_url')),
                'didit_environment' => $this->settings->get('didit_environment', config('services.didit.environment', 'live')),
                'didit_monthly_limit' => $this->settings->get('didit_monthly_limit', config('services.didit.monthly_limit', 500)),
                'didit_country_limit' => $this->settings->get('didit_country_limit', config('services.didit.country_limit', 100)),
                'dodo_api_key' => $this->settings->masked('dodo_api_key', config('services.dodo.api_key')),
                'dodo_webhook_secret' => $this->settings->masked('dodo_webhook_secret', config('services.dodo.webhook_secret')),
                'dodo_verification_payment_url' => $this->settings->get('dodo_verification_payment_url', config('services.dodo.verification_payment_url')),
                'dodo_verification_amount' => $this->settings->get('dodo_verification_amount', config('services.dodo.verification_amount', 5)),
                'dodo_verification_currency' => $this->settings->get('dodo_verification_currency', config('services.dodo.verification_currency', 'USD')),
                'kyc_waitlist_enabled' => $this->settings->get('kyc_waitlist_enabled', true),
                'kyc_waitlist_message' => $this->settings->get('kyc_waitlist_message', 'Join the KYC waiting list to reserve your place for the next country batch.'),
                'kyc_verified_mining_boost_percent' => $this->settings->get('kyc_verified_mining_boost_percent', 10),
                'brc_transfer_treasury_username' => $this->settings->get('brc_transfer_treasury_username', ''),
                'boost_store_enabled' => $this->settings->boolean('boost_store_enabled', false),
                'boost_checkout_enabled' => $this->settings->boolean('boost_checkout_enabled', false),
                'google_play_service_account_configured' => $this->settings->has('google_play_service_account_json'),
                'google_play_package_name' => $this->settings->get('google_play_package_name', 'com.blockrium'),
                'google_play_rtdn_audience' => $this->settings->get('google_play_rtdn_audience', url('/api/webhooks/google-play')),
                'google_play_rtdn_service_account_email' => $this->settings->get('google_play_rtdn_service_account_email', ''),
                'google_play_kyc_product_id' => $this->settings->get('google_play_kyc_product_id', 'kyc_verification_fee'),
                'google_play_kyc_enabled' => $this->settings->boolean('google_play_kyc_enabled', false),
            ],
            'googlePlayBoostPlans' => $googlePlayBoostPlans,
        ]);
    }

    public function waitlist(Request $request)
    {
        $countries = $this->members->waitlistCountrySummary();
        $selectedCountry = strtoupper(trim((string) $request->query('country_code', '')));
        if (!preg_match('/^[A-Z]{3}$/', $selectedCountry)) {
            $selectedCountry = '';
        }
        if ($selectedCountry === '' && $countries->isNotEmpty()) {
            $selectedCountry = (string) $countries->first()->country_code;
        }

        $activeSummary = $countries->firstWhere('country_code', $selectedCountry);
        $entries = collect();
        if ($selectedCountry !== '' && Schema::hasTable('kyc_waitlist_entries')) {
            $entryQuery = DB::table('kyc_waitlist_entries as w')
                ->leftJoin('res_users as u', 'u.id', '=', 'w.user_id')
                ->where('w.country_code', $selectedCountry)
                ->orderBy('w.joined_at')
                ->orderBy('w.id');
            if (Schema::hasTable('verified_memberships')) {
                $entryQuery->leftJoin('verified_memberships as m', 'm.user_id', '=', 'w.user_id')
                    ->select('w.*', 'u.name', 'u.username as legacy_username', 'u.email', 'm.kyc_status', 'm.didit_status', 'm.payment_status');
            } else {
                $entryQuery->select('w.*', 'u.name', 'u.username as legacy_username', 'u.email');
            }
            $entries = $entryQuery->get()->map(function ($entry) {
                $entry->kyc_status = $entry->kyc_status ?? null;
                $entry->didit_status = $entry->didit_status ?? null;
                $entry->payment_status = $entry->payment_status ?? 'pending';
                return $entry;
            });
        }
        $waitingUsers = $entries->where('status', 'waiting')->values();

        $countryLimit = max(1, (int) $this->settings->get(
            'didit_country_limit',
            config('services.didit.country_limit', 100)
        ));
        $approvedCount = 0;
        if ($selectedCountry !== '' && Schema::hasTable('verified_memberships')) {
            $approvedCount = (int) DB::table('verified_memberships')
                ->where('kyc_country', $selectedCountry)
                ->where('kyc_status', 'approved')
                ->count();
        }
        $availableSlots = max(0, $countryLimit - $approvedCount);

        return view('verified-members.waitlist', [
            'countries' => $countries,
            'selectedCountry' => $selectedCountry,
            'activeSummary' => $activeSummary,
            'entries' => $entries,
            'waitingUsers' => $waitingUsers,
            'selectedUsers' => [],
            'selectedUserIds' => [],
            'countryLimit' => $countryLimit,
            'availableSlots' => $availableSlots,
            'inviteLimit' => min(100, $waitingUsers->count(), $availableSlots),
        ]);
    }

    public function inviteWaitlist(Request $request)
    {
        $data = $request->validate([
            'country_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'limit' => 'required|integer|min:1|max:500',
        ]);

        $count = $this->members->inviteWaitlist($data['country_code'], (int) $data['limit'], $this->adminId());
        $this->audit->recordAdminAudit($this->adminId(), 'invite', 'kyc_waitlist', null, [
            'country_code' => strtoupper($data['country_code']),
            'count' => $count,
        ]);

        return back()->with('success', $count . ' waiting-list user(s) were invited to start KYC.');
    }

    public function inviteSelectedWaitlist(Request $request)
    {
        $selected = $request->input('user_ids', $request->input('selected_user_ids', $request->input('selected_users', [])));
        $selected = is_array($selected) ? $selected : [$selected];
        $entryIds = $request->input('entry_ids', []);
        $entryIds = is_array($entryIds) ? $entryIds : [$entryIds];
        $entryIds = collect($entryIds)
            ->map(function ($entryId) {
                return (int) $entryId;
            })
            ->filter(function ($entryId) {
                return $entryId > 0;
            })
            ->unique()
            ->values()
            ->all();

        if (!$selected && $entryIds && Schema::hasTable('kyc_waitlist_entries')) {
            $entryQuery = DB::table('kyc_waitlist_entries')
                ->whereIn('id', $entryIds)
                ->where('status', 'waiting');
            if ($request->filled('country_code')) {
                $entryQuery->where('country_code', strtoupper((string) $request->input('country_code')));
            }
            $selected = $entryQuery->pluck('user_id')->all();
        }
        $selected = collect($selected)
            ->map(function ($userId) {
                return (int) $userId;
            })
            ->filter(function ($userId) {
                return $userId > 0;
            })
            ->unique()
            ->values()
            ->all();

        $data = $request->merge(['user_ids' => $selected])->validate([
            'user_ids' => 'required|array|min:1|max:500',
            'user_ids.*' => 'integer|min:1',
        ]);

        $count = $this->members->inviteSelectedWaitlist($data['user_ids'], $this->adminId());
        $this->audit->recordAdminAudit($this->adminId(), 'invite_selected', 'kyc_waitlist', null, [
            'count' => $count,
        ]);

        return back()->with('success', $count . ' selected waiting-list user(s) were invited to start KYC.');
    }

    public function resendWaitlistInvite(int $userId)
    {
        $result = $this->members->resendWaitlistInvitation($userId);
        $this->audit->recordAdminAudit($this->adminId(), 'resend_invitation', 'kyc_waitlist', $userId, $result);

        return back()->with('success', 'KYC invitation reminder sent to the user.');
    }

    public function saveCountryLimit(Request $request)
    {
        $data = $request->validate([
            'country_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'member_limit' => 'required|integer|min:1|max:1000000',
        ]);

        $this->members->saveCountryLimit($data['country_code'], (int) $data['member_limit'], $this->adminId());
        $this->audit->recordAdminAudit($this->adminId(), 'update', 'kyc_country_limit', null, [
            'country_code' => strtoupper($data['country_code']),
            'member_limit' => (int) $data['member_limit'],
        ]);

        return back()->with('success', 'Country KYC capacity limit saved.');
    }

    public function paymentSupport(Request $request)
    {
        $status = $request->query('status');
        if ($status !== null && !in_array($status, ['open', 'in_review', 'resolved', 'rejected'], true)) {
            $status = null;
        }

        return view('verified-members.payment-support', [
            'cases' => $this->members->adminPaymentSupportCases($status),
            'status' => $status,
            'tableReady' => Schema::hasTable('verification_support_cases'),
        ]);
    }

    public function updatePaymentSupport(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|in:open,in_review,resolved,rejected',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        $case = $this->members->updatePaymentSupportCase($id, $data['status'], $data['admin_notes'] ?? null, $this->adminId());
        $this->audit->recordAdminAudit($this->adminId(), 'update', 'verification_support_case', $id, [
            'status' => $data['status'],
            'user_id' => $case['user_id'] ?? null,
        ]);

        return back()->with('success', 'Payment support case updated.');
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'didit_api_key' => 'nullable|string|max:500',
            'didit_webhook_secret' => 'nullable|string|max:500',
            'didit_workflow_id' => 'nullable|string|max:190',
            'didit_base_url' => 'nullable|url|max:500',
            'didit_callback_url' => 'nullable|url|max:500',
            'didit_environment' => 'required|in:live,sandbox',
            'didit_monthly_limit' => 'required|integer|min:1|max:1000000',
            'didit_country_limit' => 'required|integer|min:1|max:1000000',
            'dodo_api_key' => 'nullable|string|max:500',
            'dodo_webhook_secret' => 'nullable|string|max:500',
            'dodo_verification_payment_url' => 'nullable|url|max:500',
            'dodo_verification_amount' => 'required|numeric|min:0.01|max:100000',
            'dodo_verification_currency' => 'required|string|size:3|regex:/^[A-Za-z]{3}$/',
            'kyc_waitlist_enabled' => 'nullable|boolean',
            'kyc_waitlist_message' => 'required|string|max:500',
            'kyc_verified_mining_boost_percent' => 'required|numeric|min:0|max:100',
            'brc_transfer_treasury_username' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9_]{4,20}$/'],
            'boost_store_enabled' => 'nullable|boolean',
            'boost_checkout_enabled' => 'nullable|boolean',
            'google_play_service_account_json' => 'nullable|string|max:20000',
            'google_play_package_name' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)+$/'],
            'google_play_rtdn_audience' => 'nullable|url|max:500',
            'google_play_rtdn_service_account_email' => 'nullable|email|max:255',
            'google_play_kyc_product_id' => 'nullable|string|max:150',
            'google_play_kyc_enabled' => 'nullable|boolean',
            'google_play_boost_products' => 'nullable|array|max:50',
            'google_play_boost_products.*.product_id' => 'nullable|string|max:150',
            'google_play_boost_products.*.base_plan_id' => 'nullable|string|max:100',
        ]);

        $treasuryUsername = strtolower(trim((string) ($data['brc_transfer_treasury_username'] ?? '')));
        if ($treasuryUsername !== '') {
            $treasury = Schema::hasTable('brc_usernames')
                ? DB::table('brc_usernames')->where('normalized_username', $treasuryUsername)->first()
                : null;
            $treasuryMembership = $treasury && Schema::hasTable('verified_memberships')
                ? DB::table('verified_memberships')->where('user_id', (int) $treasury->user_id)->first()
                : null;

            if (!$treasuryMembership
                || $treasuryMembership->kyc_status !== 'approved'
                || $treasuryMembership->payment_status !== 'paid'
                || $treasuryMembership->suspended_at !== null) {
                return back()
                    ->withErrors(['brc_transfer_treasury_username' => 'Enter the username of an active, paid verified member to receive transfer fees.'])
                    ->withInput();
            }
        }

        $adminId = $this->adminId();
        if ($request->boolean('clear_google_play_service_account_json')) {
            $this->settings->forget('google_play_service_account_json');
        } elseif (!empty($data['google_play_service_account_json'])) {
            $credentials = json_decode($data['google_play_service_account_json'], true);
            if (!is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
                return back()->withErrors(['google_play_service_account_json' => 'Provide the Google service-account JSON with client_email and private_key.'])->withInput();
            }
            $this->settings->put('google_play_service_account_json', trim($data['google_play_service_account_json']), true, $adminId);
        }

        foreach (['didit_api_key', 'didit_webhook_secret', 'dodo_api_key', 'dodo_webhook_secret'] as $key) {
            if ($request->boolean('clear_' . $key)) {
                $this->settings->forget($key);
            } elseif (!empty($data[$key])) {
                $this->settings->put($key, trim($data[$key]), true, $adminId);
            }
        }

        foreach ([
            'didit_workflow_id',
            'didit_base_url',
            'didit_callback_url',
            'didit_environment',
            'didit_monthly_limit',
            'didit_country_limit',
            'dodo_verification_payment_url',
            'dodo_verification_amount',
            'dodo_verification_currency',
            'kyc_waitlist_message',
            'kyc_verified_mining_boost_percent',
            'brc_transfer_treasury_username',
            'google_play_package_name',
            'google_play_rtdn_audience',
            'google_play_rtdn_service_account_email',
            'google_play_kyc_product_id',
        ] as $key) {
            $value = isset($data[$key]) ? trim((string) $data[$key]) : '';
            if ($value === '') {
                $this->settings->forget($key);
            } else {
                $this->settings->put($key, $value, false, $adminId);
            }
        }

        $this->settings->put('kyc_waitlist_enabled', $request->boolean('kyc_waitlist_enabled') ? '1' : '0', false, $adminId);
        $this->settings->put('boost_store_enabled', $request->boolean('boost_store_enabled') ? '1' : '0', false, $adminId);
        $this->settings->put('boost_checkout_enabled', $request->boolean('boost_checkout_enabled') ? '1' : '0', false, $adminId);
        $this->settings->put('google_play_kyc_enabled', $request->boolean('google_play_kyc_enabled') ? '1' : '0', false, $adminId);

        if (Schema::hasTable('boost_plans')) {
            $activePlanIds = DB::table('boost_plans')->where('status', 1)->pluck('id')->map(function ($id) {
                return (string) $id;
            })->all();
            foreach ((array) ($data['google_play_boost_products'] ?? []) as $planId => $productData) {
                if (!in_array((string) $planId, $activePlanIds, true) || !is_array($productData)) {
                    continue;
                }
                $plan = DB::table('boost_plans')->where('id', (int) $planId)->first();
                if (!$plan) {
                    continue;
                }
                $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', (string) $plan->slug), '_'));
                foreach (['product_id', 'base_plan_id'] as $field) {
                    $key = 'google_play_' . $slug . '_' . $field;
                    $value = trim((string) ($productData[$field] ?? ''));
                    if ($value === '') {
                        $this->settings->forget($key);
                    } else {
                        $this->settings->put($key, $value, false, $adminId);
                    }
                }
            }
        }

        $this->audit->recordAdminAudit($adminId, 'update', 'integration_settings', null, [
            'didit_configured' => $this->settings->has('didit_api_key'),
            'dodo_configured' => $this->settings->has('dodo_api_key'),
        ]);

        return back()->with('success', 'Verification and payment settings saved securely.');
    }

    public function approveKyc(Request $request, int $userId)
    {
        $data = $request->validate([
            'didit_dashboard_confirmed' => 'nullable|accepted',
        ]);
        $manualDiditConfirmation = !empty($data['didit_dashboard_confirmed']);
        try {
            $status = $this->members->approveKyc($userId, $this->adminId(), $manualDiditConfirmation);
        } catch (HttpException $error) {
            return back()->withErrors(['kyc_approval' => $error->getMessage()]);
        }
        $this->audit->recordAdminAudit($this->adminId(), 'approve', 'verified_member_kyc', $userId, [
            'kyc_status' => 'approved',
            'confirmation_source' => $manualDiditConfirmation ? 'didit_dashboard_manual_confirmation' : 'didit_webhook_result',
            'payment_status' => $status['payment_status'],
        ]);

        return back()->with('success', $status['payment_status'] === 'paid'
            ? 'KYC approved. The user is verified.'
            : 'KYC approved in Blockrium. Payment remains pending and a payment reminder was added to the user account.');
    }

    public function rejectKyc(Request $request, int $userId)
    {
        $data = $request->validate(['reason' => 'required|string|max:2000']);
        $this->members->rejectKyc($userId, $this->adminId(), $data['reason']);
        $this->audit->recordAdminAudit($this->adminId(), 'reject', 'verified_member_kyc', $userId, ['kyc_status' => 'rejected', 'reason' => $data['reason']]);
        return back()->with('success', 'KYC rejected with a reason.');
    }

    public function revokeAccess(Request $request, int $userId)
    {
        $data = $request->validate([
            'scope' => 'required|in:member_access,kyc_and_member_access',
            'reason' => 'required|string|min:5|max:2000',
        ]);

        try {
            $result = $this->members->revokeAdminAccess($userId, $data['scope'], trim($data['reason']), $this->adminId());
        } catch (HttpException $error) {
            return back()->withErrors(['access_revocation' => $error->getMessage()]);
        }

        $this->audit->recordAdminAudit($this->adminId(), 'revoke_access', 'verified_member', $userId, [
            'scope' => $data['scope'],
            'reason' => trim($data['reason']),
            'kyc_status' => $result['kyc_status'],
            'payment_status' => $result['payment_status'],
            'changed' => $result['changed'],
        ]);

        if (!$result['changed']) {
            return back()->with('success', 'This access was already revoked. Payment history was not changed.');
        }

        return back()->with('success', $data['scope'] === 'member_access'
            ? 'Blockrium Verified member access was revoked. KYC approval and payment history were retained.'
            : 'KYC and Blockrium Verified member access were revoked. Payment history was retained.');
    }

    public function sendReminder(int $userId)
    {
        $result = $this->members->sendAdminReminder($userId);
        $this->audit->recordAdminAudit($this->adminId(), 'send_reminder', 'verified_member', $userId, $result);

        return back()->with(
            'success',
            $result['type'] === 'KYC_PAYMENT_REMINDER'
                ? 'Payment reminder added to the user inbox' . ($result['push_queued'] ? ' and push notification queued.' : '.')
                : 'KYC re-request added to the user inbox' . ($result['push_queued'] ? ' and push notification queued.' : '.')
        );
    }

    public function sendReminders(Request $request)
    {
        $data = $request->validate([
            'user_ids' => 'required|array|min:1|max:100',
            'user_ids.*' => 'required|integer|min:1',
        ]);

        $sent = 0;
        $skipped = 0;
        foreach (array_unique(array_map('intval', $data['user_ids'])) as $userId) {
            try {
                $this->members->sendAdminReminder($userId);
                $sent++;
            } catch (\Throwable $error) {
                report($error);
                $skipped++;
            }
        }

        $this->audit->recordAdminAudit($this->adminId(), 'send_bulk_reminders', 'verified_member', null, [
            'sent_count' => $sent,
            'skipped_count' => $skipped,
        ]);

        return back()->with('success', $sent . ' reminder(s) sent; ' . $skipped . ' account(s) skipped.');
    }

    public function confirmPayment(Request $request, int $userId)
    {
        $request->merge([
            'currency' => strtoupper(trim((string) $request->input('currency', ''))),
        ]);
        $data = $request->validate([
            'provider' => 'required|string|max:80',
            'reference' => 'required|string|max:190',
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
        ], [
            'amount.regex' => 'Enter a positive payment amount with no more than two decimal places.',
            'currency.regex' => 'Enter a valid three-letter currency code, such as USD.',
        ]);
        $this->members->confirmPayment($userId, $data['provider'], $data['reference'], (float) $data['amount'], $data['currency']);
        $this->audit->recordAdminAudit($this->adminId(), 'confirm_payment', 'verified_member_payment', $userId, ['provider' => $data['provider'], 'reference' => $data['reference'], 'amount' => $data['amount'], 'currency' => $data['currency']]);
        return back()->with('success', 'Payment confirmed. Verified badge is now active.');
    }

    private function adminId(): ?int
    {
        return Session::get('ad_id') ? (int) Session::get('ad_id') : null;
    }
}
