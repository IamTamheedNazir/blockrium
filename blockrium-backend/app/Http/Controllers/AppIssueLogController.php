<?php

namespace App\Http\Controllers;

use App\Models\AppIssueLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class AppIssueLogController extends Controller
{
    private const SENSITIVE_KEY_PATTERN = '/password|passwd|passcode|token|authorization|auth|jwt|secret|apikey|cookie|session/i';
    private const DEFAULT_METADATA_STRING_LIMIT = 1000;
    private const CRASH_STACK_STRING_LIMIT = 8000;

    public function store(Request $request)
    {
        if (!Schema::hasTable('app_issue_logs')) {
            Log::warning('App issue log received before app_issue_logs table exists.');

            return response()->json([
                'success' => false,
                'message' => 'App issue logging is not ready.',
            ], 503);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|min:1',
            'severity' => 'nullable|string|in:info,warning,error,fatal',
            'source' => 'nullable|string|max:40',
            'action' => 'nullable|string|max:80',
            'endpoint' => 'nullable|string|max:190',
            'method' => 'nullable|string|max:12',
            'http_status' => 'nullable|integer|min:100|max:599',
            'error_code' => 'nullable|string|max:80',
            'message' => 'nullable|string|max:4000',
            'app_version' => 'nullable|string|max:40',
            'build_number' => 'nullable|string|max:40',
            'platform' => 'nullable|string|max:40',
            'device_model' => 'nullable|string|max:120',
            'os_version' => 'nullable|string|max:80',
            'request_duration_ms' => 'nullable|integer|min:0|max:120000',
            'metadata' => 'nullable|array',
            'occurred_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid issue log payload.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['severity'] = $data['severity'] ?? 'error';
        $data['source'] = $data['source'] ?? 'app';
        $data['method'] = isset($data['method']) ? strtoupper($data['method']) : null;
        $data['message'] = isset($data['message']) ? substr($this->redactText($data['message']), 0, 4000) : null;
        $data['endpoint'] = isset($data['endpoint']) ? substr($this->redactText($data['endpoint']), 0, 190) : null;
        $data['metadata'] = $this->sanitizeMetadata($data['metadata'] ?? []);
        $data['user_id'] = $this->resolveUserId($request);
        $data['ip_address'] = substr((string) $request->ip(), 0, 64);
        $data['user_agent'] = substr((string) $request->userAgent(), 0, 1000);

        AppIssueLog::create($data);

        return response()->json(['success' => true], 201);
    }

    private function resolveUserId(Request $request)
    {
        foreach (['api_res_user', 'api'] as $guard) {
            try {
                $user = auth($guard)->user();

                if ($user && $user->id) {
                    return $user->id;
                }
            } catch (\Throwable $exception) {
                // Invalid or missing JWT should not block issue logging.
            }
        }

        return null;
    }

    private function sanitizeMetadata(array $metadata)
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $normalizedKey = preg_replace('/[^a-z0-9]/i', '', (string) $key);
            if (preg_match(self::SENSITIVE_KEY_PATTERN, $normalizedKey)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeMetadata($value);
                continue;
            }

            if (is_string($value)) {
                $limit = in_array(strtolower((string) $key), ['stack', 'componentstack'], true)
                    ? self::CRASH_STACK_STRING_LIMIT
                    : self::DEFAULT_METADATA_STRING_LIMIT;
                $sanitized[$key] = substr($this->redactText($value), 0, $limit);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function redactText(string $value): string
    {
        $value = preg_replace('/\b(Bearer\s+)[A-Za-z0-9._~+\/-]+=*/i', '$1[redacted]', $value);

        return preg_replace(
            '/([?&\s"\'`])(access[_-]?token|refresh[_-]?token|id[_-]?token|api[_-]?key|client[_-]?secret|password|secret|authorization)=([^&\s"\'`]+)/i',
            '$1$2=[redacted]',
            $value
        );
    }
}
