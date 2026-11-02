<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DiditService
{
    private $settings;

    public function __construct(IntegrationSettingsService $settings)
    {
        $this->settings = $settings;
    }

    public function createSession($user, string $vendorData): array
    {
        $apiKey = (string) $this->settings->get('didit_api_key', config('services.didit.api_key'));
        $workflowId = (string) $this->settings->get('didit_workflow_id', config('services.didit.workflow_id'));
        if ($apiKey === '' || $workflowId === '') {
            throw new HttpException(503, 'Didit verification is not configured yet.');
        }

        $contactDetails = [
            'send_notification_emails' => false,
            'email_lang' => 'en',
        ];
        if (!empty($user->email)) {
            $contactDetails['email'] = $user->email;
        }

        $callback = $this->settings->get('didit_callback_url', config('services.didit.callback_url'));
        $payload = [
            'workflow_id' => $workflowId,
            'vendor_data' => $vendorData,
            'metadata' => ['product' => 'blockrium_verified_member'],
            'language' => 'en',
            'contact_details' => $contactDetails,
        ];
        if ($callback) {
            $payload['callback'] = $callback;
            $payload['callback_method'] = 'both';
        } else {
            unset($payload['callback'], $payload['callback_method']);
        }

        $baseUrl = (string) $this->settings->get('didit_base_url', config('services.didit.base_url'));
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(15)->post(rtrim($baseUrl, '/') . '/session/', $payload);

        if (!$response->successful()) {
            Log::warning('Didit session creation failed.', ['status' => $response->status()]);
            throw new HttpException(502, 'Didit could not start verification. Please try again.');
        }

        $data = $response->json();
        if (!is_array($data) || empty($data['session_id']) || empty($data['url'])) {
            Log::warning('Didit returned an incomplete session response.');
            throw new HttpException(502, 'Didit returned an invalid verification session.');
        }

        return [
            'session_id' => (string) $data['session_id'],
            'url' => (string) $data['url'],
            'status' => isset($data['status']) ? (string) $data['status'] : 'Not Started',
        ];
    }

    public function listRecentUserSessions(int $limit = 100, int $offset = 0): array
    {
        $apiKey = (string) $this->settings->get('didit_api_key', config('services.didit.api_key'));
        if ($apiKey === '') {
            throw new HttpException(503, 'Didit verification is not configured yet.');
        }

        $baseUrl = (string) $this->settings->get('didit_base_url', config('services.didit.base_url'));
        $response = Http::withHeaders(['x-api-key' => $apiKey])
            ->timeout(12)
            ->get(rtrim($baseUrl, '/') . '/sessions/', [
                'session_kind' => 'user',
                'limit' => min(100, max(1, $limit)),
            'offset' => max(0, $offset),
            ]);

        if (!$response->successful()) {
            Log::warning('Didit session reconciliation request failed.', ['status' => $response->status()]);
            throw new HttpException(502, 'Didit session list could not be loaded. Please try again.');
        }

        $data = $response->json();
        if (!is_array($data) || !isset($data['results']) || !is_array($data['results'])) {
            Log::warning('Didit returned an invalid session list response.');
            throw new HttpException(502, 'Didit returned an invalid session list. Please try again.');
        }

        $hasMore = !empty($data['next']);
        $nextOffset = max(0, $offset);
        if ($hasMore) {
            $nextQuery = [];
            parse_str((string) parse_url((string) $data['next'], PHP_URL_QUERY), $nextQuery);
            $providerOffset = filter_var($nextQuery['offset'] ?? null, FILTER_VALIDATE_INT);
            $nextOffset = $providerOffset !== false && $providerOffset > $offset
                ? $providerOffset
                : max(0, $offset) + min(100, max(1, $limit));
        }

        return [
            'count' => max(0, (int) ($data['count'] ?? count($data['results']))),
            'offset' => max(0, $offset),
            'next_offset' => $nextOffset,
            'results' => array_values(array_filter($data['results'], function ($session) {
                return is_array($session)
                    && !empty($session['session_id'])
                    && isset($session['status']);
            })),
            'has_more' => $hasMore,
        ];
    }

    public function verifyWebhook(string $rawBody, array $body, ?string $signatureV2, ?string $signatureRaw, ?string $timestamp): bool
    {
        $secret = (string) $this->settings->get('didit_webhook_secret', config('services.didit.webhook_secret'));
        if ($secret === '' || !$timestamp || !is_numeric($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        if ($signatureV2) {
            $canonicalBody = json_decode($rawBody);
            $canonical = $this->canonicalJson($canonicalBody === null ? $body : $canonicalBody);
            $expected = hash_hmac('sha256', $canonical, $secret);
            if (hash_equals(strtolower($expected), strtolower($signatureV2))) {
                return true;
            }
        }

        if ($signatureRaw) {
            $expected = hash_hmac('sha256', $rawBody, $secret);
            return hash_equals(strtolower($expected), strtolower($signatureRaw));
        }

        return false;
    }

    private function canonicalJson($value): string
    {
        if (is_object($value)) {
            return $this->canonicalObject(get_object_vars($value));
        }

        if (is_array($value)) {
            if ($this->isList($value)) {
                $items = [];
                foreach ($value as $item) {
                    $items[] = $this->canonicalValue($item);
                }
                return '[' . implode(',', $items) . ']';
            }

            return $this->canonicalObject($value);
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function canonicalObject(array $value): string
    {
        $keys = array_keys($value);
        usort($keys, function ($left, $right) {
            return strcmp((string) $left, (string) $right);
        });
        $items = [];
        foreach ($keys as $key) {
            $items[] = json_encode((string) $key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ':' . $this->canonicalValue($value[$key]);
        }
        return '{' . implode(',', $items) . '}';
    }

    private function canonicalValue($value): string
    {
        if (is_array($value) || is_object($value)) {
            return $this->canonicalJson($value);
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }
}
