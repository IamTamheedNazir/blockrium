<?php

namespace App\Http\Controllers;

use App\Models\AppEngagementEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class AppEngagementEventController extends Controller
{
    public function store(Request $request)
    {
        if (!Schema::hasTable('app_engagement_events')) {
            Log::warning('App engagement event received before app_engagement_events table exists.');

            return response()->json([
                'success' => false,
                'message' => 'Engagement tracking is not ready.',
            ], 503);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|min:1',
            'event_name' => 'required|string|max:80',
            'area' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:40',
            'source' => 'nullable|string|max:40',
            'route' => 'nullable|string|max:120',
            'endpoint' => 'nullable|string|max:190',
            'duration_ms' => 'nullable|integer|min:0|max:120000',
            'app_version' => 'nullable|string|max:40',
            'build_number' => 'nullable|string|max:40',
            'platform' => 'nullable|string|max:40',
            'device_model' => 'nullable|string|max:120',
            'os_version' => 'nullable|string|max:80',
            'metadata' => 'nullable|array',
            'occurred_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid engagement event payload.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['event_name'] = $this->normalizeEventName($data['event_name']);
        $data['area'] = $data['area'] ?? $this->inferArea($data['event_name']);
        $data['status'] = $data['status'] ?? 'info';
        $data['source'] = $data['source'] ?? 'app';
        $data['metadata'] = $this->sanitizeMetadata($data['metadata'] ?? []);
        $data['user_id'] = $this->resolveUserId($request, $data['user_id'] ?? null);
        $data['ip_address'] = substr((string) $request->ip(), 0, 64);
        $data['user_agent'] = substr((string) $request->userAgent(), 0, 1000);

        AppEngagementEvent::create($data);

        return response()->json(['success' => true], 201);
    }

    private function normalizeEventName(string $eventName): string
    {
        return substr(strtolower(preg_replace('/[^a-zA-Z0-9_.-]+/', '_', trim($eventName))), 0, 80);
    }

    private function inferArea(string $eventName): string
    {
        foreach (['login', 'mining', 'task', 'squad', 'streak', 'wallet'] as $area) {
            if (strpos($eventName, $area) !== false) {
                return $area;
            }
        }

        return 'engagement';
    }

    private function resolveUserId(Request $request, $fallback)
    {
        foreach (['api_res_user', 'api'] as $guard) {
            try {
                $user = auth($guard)->user();

                if ($user && $user->id) {
                    return $user->id;
                }
            } catch (\Throwable $exception) {
                // Missing/invalid auth should not block anonymous drop-off analytics.
            }
        }

        return $fallback ?: null;
    }

    private function sanitizeMetadata(array $metadata)
    {
        $blocked = ['password', 'token', 'authToken', 'authorization', 'Authorization', 'jwt', 'secret'];
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            if (in_array((string) $key, $blocked, true)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeMetadata($value);
                continue;
            }

            if (is_string($value)) {
                $sanitized[$key] = substr($value, 0, 1000);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
